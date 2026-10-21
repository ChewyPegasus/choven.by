<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Order;
use App\Entity\Tour;
use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testNewOrderHasReferenceAndSecretToken(): void
    {
        $order = new Order();

        self::assertMatchesRegularExpression('/^CH-[2-9A-HJKMNP-Z]{6}$/', $order->getReference());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $order->getAccessToken());
        self::assertNotSame((new Order())->getAccessToken(), $order->getAccessToken());
        self::assertSame(OrderStatus::NEW, $order->getStatus());
    }

    public function testEndDateIsInclusive(): void
    {
        $order = (new Order())->setStartDate(new \DateTimeImmutable('2026-07-10 15:30'))->setDurationDays(3);

        self::assertSame('2026-07-10 00:00', $order->getStartDate()?->format('Y-m-d H:i'));
        self::assertSame('2026-07-12', $order->getEndDate()?->format('Y-m-d'));
    }

    public function testCancellationTimestamp(): void
    {
        $order = new Order();
        $order->setStatus(OrderStatus::CANCELLED);
        self::assertNotNull($order->getCancelledAt());

        $order->setStatus(OrderStatus::CONFIRMED);
        self::assertNull($order->getCancelledAt());
    }

    public function testOnlyUpcomingActiveOrdersCanBeCancelledByCustomer(): void
    {
        $upcoming = (new Order())->setStartDate(new \DateTimeImmutable('+3 days'));
        self::assertTrue($upcoming->canBeCancelledByCustomer());

        $past = (new Order())->setStartDate(new \DateTimeImmutable('-3 days'));
        self::assertFalse($past->canBeCancelledByCustomer());

        $completed = (new Order())->setStartDate(new \DateTimeImmutable('+3 days'))->setStatus(OrderStatus::COMPLETED);
        self::assertFalse($completed->canBeCancelledByCustomer());
    }

    public function testTourPriceEstimate(): void
    {
        $tour = (new Tour())->setPriceFrom(150);
        self::assertSame(600, $tour->estimatePrice(4));

        $tour->setPriceUnit(Tour::PRICE_PER_GROUP);
        self::assertSame(150, $tour->estimatePrice(4));

        self::assertNull((new Tour())->estimatePrice(4));
    }
}
