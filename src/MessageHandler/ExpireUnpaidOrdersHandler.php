<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Message\ExpireUnpaidOrders;
use App\Repository\OrderRepository;
use App\Service\BookingService;
use App\Service\PaymentService;
use App\Service\SiteSettings;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExpireUnpaidOrdersHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly BookingService $bookingService,
        private readonly PaymentService $payments,
        private readonly SiteSettings $settings,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ExpireUnpaidOrders $message): int
    {
        $now = $this->clock->now();
        // A payment page left open for a day will not be paid anymore.
        $this->payments->cancelStale($now->modify('-1 day'));

        if (!$this->settings->isPrepaymentRequired() || !$this->payments->isEnabled()) {
            return 0;
        }

        /** @var list<Order> $candidates */
        $candidates = $this->orders->createQueryBuilder('o')
            ->andWhere('o.status = :new')
            ->andWhere('o.depositAmount IS NOT NULL')
            ->andWhere('o.paidAmount < o.depositAmount')
            ->andWhere('o.createdAt < :before')
            ->setParameter('new', OrderStatus::NEW)
            ->setParameter('before', $now->modify(\sprintf('-%d hours', $this->settings->getPrepaymentHoldHours())))
            ->getQuery()
            ->getResult();

        $expired = 0;
        foreach ($candidates as $order) {
            // Pending payment: the customer may be paying right now (ERIP takes time), check first.
            foreach ($order->getPayments() as $payment) {
                $this->payments->refresh($payment);
            }
            if ($order->isDepositPaid() || OrderStatus::NEW !== $order->getStatus()) {
                continue;
            }

            $this->bookingService->expireUnpaid($order);
            ++$expired;
        }

        return $expired;
    }
}
