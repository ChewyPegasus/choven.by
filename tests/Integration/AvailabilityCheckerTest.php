<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\OrderStatus;
use App\Service\AvailabilityChecker;
use App\Service\SiteSettings;
use App\Tests\Support\Factory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * BOOKING_DAILY_CAPACITY is 20 in .env.test.
 */
final class AvailabilityCheckerTest extends KernelTestCase
{
    private AvailabilityChecker $checker;
    private Factory $factory;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->checker = $container->get(AvailabilityChecker::class);
        $this->factory = new Factory($container->get(EntityManagerInterface::class), $container->get(UserPasswordHasherInterface::class));
    }

    public function testFreeDayIsAvailable(): void
    {
        self::assertSame([], $this->checker->check(new \DateTimeImmutable('+5 days'), 1, 20));
    }

    public function testCapacityIsSharedByOverlappingMultiDayTrips(): void
    {
        $this->factory->order('+10 days', people: 12, days: 3); // days 10, 11, 12
        $this->factory->order('+12 days', people: 6);            // day 12

        self::assertSame([], $this->checker->check(new \DateTimeImmutable('+11 days'), 1, 8));

        $problems = $this->checker->check(new \DateTimeImmutable('+11 days'), 2, 8);
        self::assertSame([(new \DateTimeImmutable('+12 days'))->format('Y-m-d') => 2], $problems);
    }

    public function testCancelledOrdersDoNotTakePlaces(): void
    {
        $this->factory->order('+20 days', people: 20, status: OrderStatus::CANCELLED);

        self::assertSame([], $this->checker->check(new \DateTimeImmutable('+20 days'), 1, 20));
    }

    public function testBlockedDates(): void
    {
        $day = new \DateTimeImmutable('+30 days');
        $settings = self::getContainer()->get(SiteSettings::class);
        $settings->save([SiteSettings::BLOCKED_DATES => [$day->format('Y-m-d')]]);

        self::assertSame([$day->format('Y-m-d') => 'blocked'], $this->checker->check($day, 1, 1));

        $calendar = $this->checker->getCalendar($day->modify('-1 day'), $day->modify('+1 day'));
        self::assertTrue($calendar['days'][$day->format('Y-m-d')]['blocked']);
        self::assertSame(20, $calendar['capacity']);
    }
}
