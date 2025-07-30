<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

use App\Entity\FailedEmail; // Assuming FailedEmail entity is managed by this repository

/**
 * Interface for the FailedEmailRepository.
 *
 * Defines the contract for a repository that manages `FailedEmail` entities,
 * providing methods for basic persistence operations, counting, and finding entities.
 */
interface FailedEmailRepositoryInterface
{
    /**
     * Persists a FailedEmail entity to the database.
     *
     * This method should handle both new and existing entities.
     *
     * @param FailedEmail $failedEmail The FailedEmail entity to save.
     */
    public function save(FailedEmail $failedEmail): void;

    /**
     * Removes a FailedEmail entity from the database.
     *
     * @param FailedEmail $failedEmail The FailedEmail entity to remove.
     */
    public function remove(FailedEmail $failedEmail): void;

    /**
     * Flushes all pending changes to the database.
     *
     * This method commits all changes tracked by the Entity Manager to the database.
     */
    public function flush(): void;

    /**
     * Counts the number of FailedEmail entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @return int The number of entities matching the criteria.
     */
    public function count(array $criteria = []): int;

    /**
     * Finds FailedEmail entities by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses (e.g., ['createdAt' => 'ASC']).
     * @param int|null $limit The maximum number of results to retrieve.
     * @param int|null $offset The offset from the beginning of the result set.
     * @return FailedEmail[] An array of FailedEmail entities.
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
}