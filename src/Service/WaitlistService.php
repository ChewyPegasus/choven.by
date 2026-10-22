<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\WaitlistEntry;
use App\Mailer\AppMailer;
use App\Repository\WaitlistEntryRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Waitlist for sold-out dates: people leave their contacts and get an email as soon as places become free.
 */
class WaitlistService
{
    public function __construct(
        private readonly WaitlistEntryRepository $entries,
        private readonly AvailabilityChecker $availability,
        private readonly AppMailer $mailer,
        private readonly TeamNotifier $teamNotifier,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool false when the same person is already waiting for that date
     */
    public function join(WaitlistEntry $entry): bool
    {
        if (null === $entry->getStartDate() || $this->entries->existsWaiting($entry->getEmail(), $entry->getStartDate())) {
            return false;
        }

        $this->entries->save($entry);
        $this->mailer->sendWaitlistJoined($entry);
        $this->teamNotifier->waitlistJoined($entry);

        return true;
    }

    /**
     * Tells waiting people about free places in the period. Each entry is notified once;
     * people who asked earlier are checked first.
     *
     * @return int number of people notified
     */
    public function notifyAvailable(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $notified = 0;
        foreach ($this->entries->findWaitingOverlapping($from, $to, $this->clock->now()->setTime(0, 0)) as $entry) {
            $start = $entry->getStartDate();
            if (null === $start || [] !== $this->availability->check($start, $entry->getDurationDays(), $entry->getPeople())) {
                continue;
            }

            $entry->markNotified();
            $this->entries->save($entry);
            $this->mailer->sendWaitlistAvailable($entry);
            ++$notified;
        }

        if ($notified > 0) {
            $this->logger->info('{count} people on the waitlist were told about free places', ['count' => $notified]);
        }

        return $notified;
    }
}
