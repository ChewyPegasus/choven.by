<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

use App\Entity\EmailQueue; // Assuming EmailQueue entity is managed by this repository

/**
 * Interface for the EmailQueueRepository.
 *
 * Defines the contract for a repository that manages `EmailQueue` entities,
 * providing methods for finding emails to retry, finding failed emails,
 * and standard persistence operations.
 */
interface EmailQueueRepositoryInterface
{
    /**
     * Finds emails that are eligible for retry.
     *
     * This method should return `EmailQueue` entities that have been attempted
     * less than `$maxAttempts` times and are ready to be resent (e.g., based on a delay).
     *
     * @param int $maxAttempts The maximum number of attempts allowed for an email.
     * @return EmailQueue[] An array of EmailQueue entities to be retried.
     */
    public function findEmailsToRetry(int $maxAttempts): array;

    /**
     * Finds emails that are considered permanently failed (e.g., exceeded max attempts).
     *
     * This method should return `EmailQueue` entities that have reached or exceeded
     * the `$maxAttempts` threshold, indicating they should no longer be retried.
     *
     * @param int $maxAttempts The maximum number of attempts allowed for an email.
     * @return EmailQueue[] An array of EmailQueue entities that have failed.
     */
    public function findFailedEmails(int $maxAttempts): array;

    /**
     * Persists an EmailQueue entity to the database.
     *
     * This method should handle both new and existing entities.
     *
     * @param EmailQueue $emailQueue The EmailQueue entity to save.
     */
    public function save(EmailQueue $emailQueue): void;

    /**
     * Removes an EmailQueue entity from the database.
     *
     * @param EmailQueue $emailQueue The EmailQueue entity to remove.
     */
    public function remove(EmailQueue $emailQueue): void;

    /**
     * Flushes all pending changes to the database.
     *
     * This method commits all changes tracked by the Entity Manager to the database.
     */
    public function flush(): void;
}