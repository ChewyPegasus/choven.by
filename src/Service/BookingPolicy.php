<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Psr\Clock\ClockInterface;

/**
 * What a customer may change online: rescheduling and free cancellation (with a refund of the prepayment)
 * are possible until "free_change_days" before the trip; later the booking can still be cancelled,
 * but the prepayment is kept.
 */
class BookingPolicy
{
    public const MAX_RESCHEDULES = 2;

    public function __construct(
        private readonly SiteSettings $settings,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getFreeChangeDays(): int
    {
        return $this->settings->getFreeChangeDays();
    }

    /**
     * Last day when the booking can be changed or cancelled for free.
     */
    public function getFreeChangeDeadline(Order $order): ?\DateTimeImmutable
    {
        return $order->getStartDate()?->sub(new \DateInterval(\sprintf('P%dD', $this->getFreeChangeDays())));
    }

    public function isWithinFreePeriod(Order $order): bool
    {
        return $order->getDaysUntilStart($this->today()) >= $this->getFreeChangeDays();
    }

    public function canCancel(Order $order): bool
    {
        return $order->canBeCancelledByCustomer();
    }

    /**
     * Whether a customer cancelling now gets the prepayment back.
     */
    public function isRefundable(Order $order): bool
    {
        return $order->getPaidAmount() > 0 && $this->isWithinFreePeriod($order);
    }

    public function canReschedule(Order $order): bool
    {
        return $order->getStatus()->isActive()
            && $order->isUpcoming()
            && $this->isWithinFreePeriod($order)
            && $order->getRescheduleCount() < self::MAX_RESCHEDULES;
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }
}
