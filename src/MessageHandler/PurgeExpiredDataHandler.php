<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeExpiredData;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeExpiredDataHandler
{
    /** Accounts that never confirmed their email are removed after this many days (frees the email and phone). */
    private const UNVERIFIED_TTL_DAYS = 7;

    public function __construct(
        private readonly ResetPasswordRequestRepository $resetRequests,
        private readonly UserRepository $users,
    ) {
    }

    public function __invoke(PurgeExpiredData $message): int
    {
        $removed = $this->resetRequests->removeExpiredResetPasswordRequests();

        return $removed + $this->users->removeUnverifiedOlderThan(new \DateTimeImmutable(\sprintf('-%d days', self::UNVERIFIED_TTL_DAYS)));
    }
}
