<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckWaitlist;
use App\Service\WaitlistService;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckWaitlistHandler
{
    public function __construct(
        private readonly WaitlistService $waitlist,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(CheckWaitlist $message): int
    {
        $today = $this->clock->now()->setTime(0, 0);
        $from = null !== $message->from ? \DateTimeImmutable::createFromFormat('!Y-m-d', $message->from) : $today;
        $to = null !== $message->to ? \DateTimeImmutable::createFromFormat('!Y-m-d', $message->to) : $today->modify('+90 days');
        if (false === $from || false === $to) {
            return 0;
        }

        return $this->waitlist->notifyAvailable($from, $to);
    }
}
