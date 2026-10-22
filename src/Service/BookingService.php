<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GiftCertificate;
use App\Entity\Order;
use App\Entity\OrderExtra;
use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Exception\DatesUnavailableException;
use App\Mailer\CustomerNotifier;
use App\Message\CheckWaitlist;
use App\Model\PriceQuote;
use App\Payment\PaymentGateways;
use App\Repository\ExtraRepository;
use App\Repository\OrderRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Business operations on bookings; controllers stay thin and always go through here.
 */
class BookingService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CustomerNotifier $customerNotifier,
        private readonly TeamNotifier $teamNotifier,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityChecker $availability,
        private readonly PriceCalculator $priceCalculator,
        private readonly ExtraRepository $extras,
        private readonly PaymentGateways $payments,
        private readonly MessageBusInterface $bus,
        private readonly BookingPolicy $policy,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Price breakdown shown next to the booking form (before the booking exists).
     *
     * @param array<int, int> $extraQuantities
     */
    public function quote(Order $order, array $extraQuantities, ?PromoCode $promo, ?GiftCertificate $gift): PriceQuote
    {
        return $this->priceCalculator->quote(
            $order->getTour(),
            (int) $order->getAmountOfPeople(),
            (int) $order->getDurationDays(),
            $extraQuantities,
            $this->extras->findActive(),
            $promo,
            $gift,
        );
    }

    /**
     * Saves a booking made on the site and notifies the customer and the team.
     *
     * @param array<int, int> $extraQuantities extra id => quantity
     *
     * @throws DatesUnavailableException when the last places were taken by a concurrent booking
     */
    public function place(
        Order $order,
        ?User $user,
        string $locale,
        array $extraQuantities = [],
        ?PromoCode $promo = null,
        ?GiftCertificate $gift = null,
    ): void {
        $order->setLocale($locale);
        $order->setStatus(OrderStatus::NEW);

        if (null !== $user) {
            // The email field is read-only for signed-in users; never trust the submitted value.
            $order->setUser($user)->setEmail($user->getEmail());
        }

        // Serialize bookings so that two customers cannot take the same last places at the same moment
        // (and cannot spend the same gift certificate or the last use of a promo code twice).
        $this->entityManager->wrapInTransaction(function () use ($order, $extraQuantities, $promo, $gift): void {
            $this->lockSchedule();

            $start = $order->getStartDate();
            if (null !== $start) {
                $problems = $this->availability->check($start, (int) $order->getDurationDays(), (int) $order->getAmountOfPeople());
                if ([] !== $problems) {
                    throw new DatesUnavailableException($problems);
                }
            }

            $today = $this->clock->now();
            if (null !== $gift) {
                $this->entityManager->refresh($gift, LockMode::PESSIMISTIC_WRITE);
                $gift = $gift->isRedeemable($today) ? $gift : null;
            }
            if (null !== $promo) {
                $this->entityManager->refresh($promo, LockMode::PESSIMISTIC_WRITE);
                $promo = $promo->isUsable($today) ? $promo : null;
            }

            $quote = $this->quote($order, $extraQuantities, $promo, $gift);
            $this->applyQuote($order, $quote, $promo, $gift);
            if ($order->getDiscount() > 0) {
                $promo?->incrementUsage();
            }
            if ($order->getGiftAmount() > 0) {
                $gift?->redeem($order->getGiftAmount());
            }

            $this->ensureUniqueReference($order);
            $this->orderRepository->save($order);
        });

        $this->logger->info('Booking {reference} placed for {date}', [
            'reference' => $order->getReference(),
            'date' => $order->getStartDate()?->format('Y-m-d'),
        ]);

        $this->customerNotifier->orderPlaced($order);
        $this->teamNotifier->newOrder($order);
    }

    /**
     * Saves a booking created by an administrator (e.g. taken over the phone).
     */
    public function createByAdmin(Order $order, bool $notifyCustomer): void
    {
        $base = $order->getTour()?->estimatePrice((int) $order->getAmountOfPeople());
        $order->setBasePrice($base);
        if (null === $order->getEstimatedPrice()) {
            $order->setEstimatedPrice($base);
        }

        $this->ensureUniqueReference($order);
        $this->orderRepository->save($order);

        if ($notifyCustomer) {
            $this->customerNotifier->orderPlaced($order);
        }
    }

    public function cancelByCustomer(Order $order): void
    {
        if (!$order->canBeCancelledByCustomer()) {
            throw new \LogicException(\sprintf('Order %s cannot be cancelled.', $order->getReference()));
        }

        $refundable = $this->policy->isRefundable($order);
        $order->setStatus(OrderStatus::CANCELLED);
        $this->release($order);
        $this->orderRepository->save($order);
        $this->checkWaitlistFor($order);

        $this->customerNotifier->statusChanged($order);
        $this->teamNotifier->orderCancelled($order, $refundable);
    }

    /**
     * Moves an upcoming booking to another start date (same duration and group size).
     *
     * @throws DatesUnavailableException
     */
    public function reschedule(Order $order, \DateTimeImmutable $newStart): void
    {
        $oldStart = $order->getStartDate();
        $oldEnd = $order->getEndDate();

        $this->entityManager->wrapInTransaction(function () use ($order, $newStart): void {
            $this->lockSchedule();
            $problems = $this->availability->check($newStart, (int) $order->getDurationDays(), (int) $order->getAmountOfPeople(), $order);
            if ([] !== $problems) {
                throw new DatesUnavailableException($problems);
            }

            $order->reschedule($newStart);
            $this->orderRepository->save($order);
        });

        $this->logger->info('Booking {reference} moved from {old} to {new}', [
            'reference' => $order->getReference(),
            'old' => $oldStart?->format('Y-m-d'),
            'new' => $newStart->format('Y-m-d'),
        ]);

        if (null !== $oldStart && null !== $oldEnd) {
            $this->checkWaitlist($oldStart, $oldEnd);
        }
        $this->customerNotifier->rescheduled($order, $oldStart);
        $this->teamNotifier->orderRescheduled($order, $oldStart);
    }

    /**
     * Applies an admin edit; when the status changed the customer can be informed.
     *
     * @param array{start: \DateTimeImmutable|null, end: \DateTimeImmutable|null, people: int|null} $previous dates and group size before the edit
     */
    public function update(Order $order, OrderStatus $previousStatus, bool $notifyCustomer, array $previous = ['start' => null, 'end' => null, 'people' => null]): void
    {
        $becameInactive = $previousStatus->isActive() && !$order->getStatus()->isActive();
        if ($becameInactive && OrderStatus::CANCELLED === $order->getStatus()) {
            $this->release($order);
        }
        $this->orderRepository->save($order);

        // Places may have become free: on the old dates, or because the group got smaller.
        $freed = $becameInactive
            || $previous['start'] != $order->getStartDate()
            || $previous['end'] != $order->getEndDate()
            || (null !== $previous['people'] && $previous['people'] > (int) $order->getAmountOfPeople());
        if ($freed && null !== $previous['start'] && null !== $previous['end'] && $previousStatus->isActive()) {
            $this->checkWaitlist($previous['start'], $previous['end']);
        }

        if (!$notifyCustomer || $previousStatus === $order->getStatus()) {
            return;
        }

        if (OrderStatus::COMPLETED === $order->getStatus()) {
            $this->customerNotifier->reviewInvitation($order);
        } else {
            $this->customerNotifier->statusChanged($order);
        }
    }

    /**
     * A booking whose required prepayment did not arrive in time.
     */
    public function expireUnpaid(Order $order): void
    {
        $order->setStatus(OrderStatus::CANCELLED);
        $order->setAdminNote(trim(($order->getAdminNote() ?? '')."\n".'Автоотмена: не поступила предоплата.'));
        $this->release($order);
        $this->orderRepository->save($order);
        $this->checkWaitlistFor($order);

        $this->customerNotifier->expired($order);
        $this->teamNotifier->orderExpired($order);
    }

    /**
     * Removes a booking for good (admin panel).
     */
    public function delete(Order $order): void
    {
        $wasActive = $order->getStatus()->isActive();
        if ($wasActive) {
            $this->release($order);
        }
        $start = $order->getStartDate();
        $end = $order->getEndDate();
        $this->orderRepository->remove($order);

        if ($wasActive && null !== $start && null !== $end) {
            $this->checkWaitlist($start, $end);
        }
    }

    /**
     * Gives back what an active booking was holding: the gift certificate balance and a promo code use.
     * The caller saves the order and then tells the waitlist about the free places.
     */
    private function release(Order $order): void
    {
        if ($order->getGiftAmount() > 0 && null !== $order->getGiftCertificate()) {
            $order->getGiftCertificate()->restore($order->getGiftAmount());
            $order->setGiftAmount(0);
        }
        if ($order->getDiscount() > 0) {
            $order->getPromoCode()?->decrementUsage();
        }
    }

    private function checkWaitlistFor(Order $order): void
    {
        if (null !== $order->getStartDate() && null !== $order->getEndDate()) {
            $this->checkWaitlist($order->getStartDate(), $order->getEndDate());
        }
    }

    private function checkWaitlist(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        try {
            $this->bus->dispatch(new CheckWaitlist($from->format('Y-m-d'), $to->format('Y-m-d')));
        } catch (\Throwable $e) {
            $this->logger->warning('Could not queue the waitlist check: {message}', ['message' => $e->getMessage()]);
        }
    }

    private function applyQuote(Order $order, PriceQuote $quote, ?PromoCode $promo, ?GiftCertificate $gift): void
    {
        $order->clearExtras();
        foreach ($quote->extras as $line) {
            $order->addExtra(new OrderExtra($order, $line['extra'], $line['quantity'], $line['total']));
        }

        $order->setBasePrice($quote->base)
            ->setExtrasPrice($quote->extrasTotal)
            ->setDiscount($quote->discount)
            ->setPromoCode($quote->discount > 0 ? $promo : null)
            ->setGiftAmount($quote->giftAmount)
            ->setGiftCertificate($quote->giftAmount > 0 ? $gift : null)
            ->setEstimatedPrice($quote->total)
            ->setDepositAmount($this->payments->isEnabled() ? $quote->deposit : null);
    }

    /**
     * Transaction-scoped lock of the booking schedule (PostgreSQL advisory lock, released on commit).
     */
    private function lockSchedule(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $connection->executeStatement('SELECT pg_advisory_xact_lock(724205)');
        }
    }

    private function ensureUniqueReference(Order $order): void
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $existing = $this->orderRepository->findOneByReference($order->getReference());
            if (null === $existing || $existing === $order) {
                return;
            }
            $order->regenerateReference();
        }

        throw new \RuntimeException('Could not generate a unique booking reference.');
    }
}
