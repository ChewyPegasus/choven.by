<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeExpiredData;
use App\Repository\ResetPasswordRequestRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeExpiredDataHandler
{
    public function __construct(private readonly ResetPasswordRequestRepository $resetRequests)
    {
    }

    public function __invoke(PurgeExpiredData $message): int
    {
        return $this->resetRequests->removeExpiredResetPasswordRequests();
    }
}
