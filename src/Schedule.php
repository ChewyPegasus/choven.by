<?php

declare(strict_types=1);

namespace App;

use App\Message\CheckWaitlist;
use App\Message\CompletePastOrders;
use App\Message\ExpireUnpaidOrders;
use App\Message\PurgeExpiredData;
use App\Message\RecordHeartbeat;
use App\Message\SendTripReminders;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Recurring jobs, executed by the worker: `messenger:consume async scheduler_default`.
 */
#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): SymfonySchedule
    {
        $timezone = new \DateTimeZone('Europe/Minsk');

        return (new SymfonySchedule())
            ->add(
                RecurringMessage::cron('0 10 * * *', new CompletePastOrders(), $timezone),
                RecurringMessage::cron('30 3 * * *', new PurgeExpiredData(), $timezone),
                RecurringMessage::cron('0 18 * * *', new SendTripReminders(), $timezone),
                RecurringMessage::cron('15 9 * * *', new CheckWaitlist(), $timezone),
                RecurringMessage::cron('5 * * * *', new ExpireUnpaidOrders(), $timezone),
                RecurringMessage::every('5 minutes', new RecordHeartbeat()),
            )
            // Remember the last run, so jobs missed during a restart are caught up and not run twice.
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
