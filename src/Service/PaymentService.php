<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GiftCertificate;
use App\Entity\Order;
use App\Entity\Payment;
use App\Enum\GiftCertificateStatus;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Mailer\AppMailer;
use App\Mailer\CustomerNotifier;
use App\Payment\CheckoutRequest;
use App\Payment\PaymentException;
use App\Payment\PaymentGatewayInterface;
use App\Payment\PaymentGateways;
use App\Payment\PaymentResult;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Online payments: booking prepayments and gift certificates.
 *
 * The provider tells us about the result twice (server notification and the customer coming back);
 * applying a result is idempotent and serialized with a row lock.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly PaymentRepository $payments,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly CustomerNotifier $customerNotifier,
        private readonly AppMailer $mailer,
        private readonly TeamNotifier $teamNotifier,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->gateways->isEnabled();
    }

    /**
     * Starts paying the outstanding prepayment of a booking; returns the payment page URL.
     *
     * @throws PaymentException
     */
    public function startForOrder(Order $order): string
    {
        $amount = $order->getDepositDue();
        if ($amount <= 0 || !$order->getStatus()->isActive()) {
            throw new PaymentException(\sprintf('Nothing to pay for booking %s.', $order->getReference()));
        }

        $description = $this->translator->trans('payment.description.order', [
            '%reference%' => $order->getReference(),
            '%date%' => $order->getStartDate()?->format('d.m.Y') ?? '',
        ], 'messages', $order->getLocale());

        $payment = (new Payment($this->gateway()->getName(), $amount))->setOrder($order);

        return $this->start($payment, $description, $order->getEmail(), $order->getLocale());
    }

    /**
     * @throws PaymentException
     */
    public function startForGiftCertificate(GiftCertificate $gift): string
    {
        if (GiftCertificateStatus::PENDING !== $gift->getStatus()) {
            throw new PaymentException('The gift certificate is already paid.');
        }

        $description = $this->translator->trans('payment.description.gift', ['%amount%' => $gift->getAmount()], 'messages', $gift->getLocale());
        $payment = (new Payment($this->gateway()->getName(), $gift->getAmount()))->setGiftCertificate($gift);

        return $this->start($payment, $description, $gift->getBuyerEmail(), $gift->getLocale());
    }

    /**
     * Asks the provider about a pending payment (the customer came back from the payment page).
     */
    public function refresh(Payment $payment): Payment
    {
        if (!$payment->isPending()) {
            return $payment;
        }

        $gateway = $this->gateways->byName($payment->getProvider());
        if (null === $gateway) {
            return $payment;
        }

        try {
            $result = $gateway->fetchStatus($payment);
        } catch (PaymentException $e) {
            $this->logger->error('Could not fetch the status of payment {id}: {message}', ['id' => $payment->getPublicId(), 'message' => $e->getMessage()]);

            return $payment;
        }

        if (null !== $result) {
            $this->apply($payment, $result);
        }

        return $payment;
    }

    /**
     * Applies a status reported by the provider. Safe to call several times.
     */
    public function apply(Payment $payment, PaymentResult $result): void
    {
        $succeeded = false;

        $this->entityManager->wrapInTransaction(function () use ($payment, $result, &$succeeded): void {
            $this->entityManager->refresh($payment, LockMode::PESSIMISTIC_WRITE);
            if (!$payment->isPending()) {
                return;
            }

            if (PaymentStatus::SUCCEEDED !== $result->status) {
                $payment->markFailed($result->status, $result->message);
                $this->entityManager->flush();

                return;
            }

            $payment->markSucceeded($result->transactionId);
            $order = $payment->getOrder();
            if (null !== $order) {
                $order->addPaid($payment->getAmount());
                // A paid prepayment confirms the booking.
                if (OrderStatus::NEW === $order->getStatus() && $order->isDepositPaid()) {
                    $order->setStatus(OrderStatus::CONFIRMED);
                }
            }
            $payment->getGiftCertificate()?->activate();
            $this->entityManager->flush();
            $succeeded = true;
        });

        if (!$succeeded) {
            return;
        }

        $this->logger->info('Payment {id} of {amount} BYN succeeded', ['id' => $payment->getPublicId(), 'amount' => $payment->getAmount()]);
        if (null !== $payment->getOrder()) {
            $this->customerNotifier->paymentReceived($payment->getOrder(), $payment);
        }
        if (null !== $payment->getGiftCertificate()) {
            $this->mailer->sendGiftCertificate($payment->getGiftCertificate());
        }
        $this->teamNotifier->paymentReceived($payment);
    }

    /**
     * Returns money of a successful payment to the customer (admin action).
     *
     * @throws PaymentException
     */
    public function refund(Payment $payment, ?int $amount = null, string $reason = 'Отмена бронирования'): int
    {
        $amount = min($amount ?? $payment->getRefundableAmount(), $payment->getRefundableAmount());
        if ($amount <= 0) {
            throw new PaymentException('Nothing to refund.');
        }

        $gateway = $this->gateways->byName($payment->getProvider())
            ?? throw new PaymentException(\sprintf('Payment provider "%s" is not configured.', $payment->getProvider()));
        $gateway->refund($payment, $amount, $reason);

        $payment->markRefunded($amount);
        $payment->getOrder()?->addPaid(-$amount);
        $this->entityManager->flush();

        $this->audit->log('payment_refund', $payment, ['amount' => $amount, 'reason' => $reason]);
        $this->logger->info('Payment {id}: {amount} BYN refunded', ['id' => $payment->getPublicId(), 'amount' => $amount]);

        return $amount;
    }

    /**
     * Abandoned checkouts are marked as cancelled after a while (they no longer show as "pending").
     */
    public function cancelStale(\DateTimeImmutable $before): int
    {
        $count = 0;
        foreach ($this->payments->findStalePending($before) as $payment) {
            $this->refresh($payment);
            if ($payment->isPending()) {
                $payment->markFailed(PaymentStatus::CANCELLED, 'Checkout abandoned');
                ++$count;
            }
        }
        $this->entityManager->flush();

        return $count;
    }

    private function start(Payment $payment, string $description, string $email, string $locale): string
    {
        $gateway = $this->gateway();
        // The id is needed by some payment methods (ERIP account number).
        $this->payments->save($payment);

        $returnUrl = $this->urlGenerator->generate('app_payment_return', ['_locale' => $locale, 'publicId' => $payment->getPublicId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $notifyUrl = $this->urlGenerator->generate('app_payment_notify', ['provider' => $gateway->getName()], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $gateway->createCheckout($payment, new CheckoutRequest($description, $email, $locale, $returnUrl, $notifyUrl));
        } catch (PaymentException $e) {
            $payment->markFailed(PaymentStatus::FAILED, $e->getMessage());
            $this->payments->save($payment);
            $this->logger->error('Could not start payment {id}: {message}', ['id' => $payment->getPublicId(), 'message' => $e->getMessage()]);

            throw $e;
        }

        $this->payments->save($payment);

        return (string) $payment->getRedirectUrl();
    }

    private function gateway(): PaymentGatewayInterface
    {
        return $this->gateways->get() ?? throw new PaymentException('Online payments are not configured.');
    }
}
