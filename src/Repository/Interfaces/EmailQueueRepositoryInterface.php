<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

interface EmailQueueRepositoryInterface
{
    /**
     * Find emails that need to be retried
     * Limit to emails that have been attempted less than MAX_ATTEMPTS times 
     */
    public function findEmailsToRetry(int $maxAttempts): array;

    public function findFailedEmails(int $maxAttempts): array;

    public function save($emailQueue): void;

    public function remove($emailQueue): void;

    public function flush(): void;
}
