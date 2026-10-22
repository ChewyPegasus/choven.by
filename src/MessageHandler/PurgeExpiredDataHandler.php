<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeExpiredData;
use App\Repository\AuditLogRepository;
use App\Repository\GiftCertificateRepository;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use App\Repository\WaitlistEntryRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeExpiredDataHandler
{
    /** Accounts that never confirmed their email are removed after this many days (frees the email and phone). */
    private const UNVERIFIED_TTL_DAYS = 7;
    /** Gift certificates ordered but never paid. */
    private const UNPAID_GIFT_TTL_DAYS = 7;
    private const AUDIT_LOG_TTL = '-1 year';

    public function __construct(
        private readonly ResetPasswordRequestRepository $resetRequests,
        private readonly UserRepository $users,
        private readonly WaitlistEntryRepository $waitlist,
        private readonly GiftCertificateRepository $giftCertificates,
        private readonly AuditLogRepository $auditLog,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(PurgeExpiredData $message): int
    {
        $now = $this->clock->now();

        return $this->resetRequests->removeExpiredResetPasswordRequests()
            + $this->users->removeUnverifiedOlderThan($now->modify(\sprintf('-%d days', self::UNVERIFIED_TTL_DAYS)))
            + $this->waitlist->removePast($now->setTime(0, 0))
            + $this->giftCertificates->removePendingOlderThan($now->modify(\sprintf('-%d days', self::UNPAID_GIFT_TTL_DAYS)))
            + $this->auditLog->removeOlderThan($now->modify(self::AUDIT_LOG_TTL));
    }
}
