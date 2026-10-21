<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;

/**
 * Answers "can we take N more people on these dates?" using the daily capacity
 * and the list of blocked dates from the site settings.
 */
class AvailabilityChecker
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly SiteSettings $settings,
    ) {
    }

    /**
     * Returns the list of problems ("Y-m-d" => reason) for the requested trip; empty means available.
     *
     * Reason is "blocked" or the number of places still free that day.
     *
     * @return array<string, string|int>
     */
    public function check(\DateTimeImmutable $start, int $days, int $people, ?Order $exclude = null): array
    {
        $days = max(Order::MIN_DAYS, min(Order::MAX_DAYS, $days));
        $end = $start->modify(\sprintf('+%d days', max(0, $days - 1)));
        $capacity = $this->settings->getDailyCapacity();
        $booked = $capacity > 0 ? $this->orderRepository->getBookedPeopleByDay($start, $end, $exclude) : [];

        $problems = [];
        for ($day = $start->setTime(0, 0); $day <= $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            if ($this->settings->isDateBlocked($day)) {
                $problems[$key] = 'blocked';
                continue;
            }

            if ($capacity > 0) {
                $free = $capacity - ($booked[$key] ?? 0);
                if ($free < $people) {
                    $problems[$key] = max(0, $free);
                }
            }
        }

        return $problems;
    }

    /**
     * Calendar data for the date picker: days that are blocked or (almost) sold out.
     *
     * @return array{capacity: int, days: array<string, array{free: int|null, blocked: bool}>}
     */
    public function getCalendar(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $capacity = $this->settings->getDailyCapacity();
        $booked = $capacity > 0 ? $this->orderRepository->getBookedPeopleByDay($from, $to) : [];
        $blocked = array_flip($this->settings->getBlockedDates());

        $days = [];
        for ($day = $from->setTime(0, 0); $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $isBlocked = isset($blocked[$key]);
            $free = $capacity > 0 ? max(0, $capacity - ($booked[$key] ?? 0)) : null;
            if ($isBlocked || (null !== $free && $free < $capacity)) {
                $days[$key] = ['free' => $isBlocked ? 0 : $free, 'blocked' => $isBlocked];
            }
        }

        return ['capacity' => $capacity, 'days' => $days];
    }
}
