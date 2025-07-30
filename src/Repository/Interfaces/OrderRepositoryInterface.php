<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\DBAL\LockMode; // Import LockMode for type hinting

/**
 * Interface for the OrderRepository.
 *
 * Defines the contract for a repository that manages `Order` entities,
 * providing methods for finding orders based on user and date, as well as
 * standard persistence operations and retrieval by ID.
 */
interface OrderRepositoryInterface
{
    /**
     * Finds all upcoming orders for a given user.
     *
     * An "upcoming" order is typically one whose start date is in the future.
     *
     * @param User $user The user for whom to find upcoming orders.
     * @return Order[] An array of upcoming Order entities.
     */
    public function findUpcomingOrdersByUser(User $user): array;

    /**
     * Finds all past orders for a given user.
     *
     * A "past" order is typically one whose start date has already passed.
     *
     * @param User $user The user for whom to find past orders.
     * @return Order[] An array of past Order entities.
     */
    public function findPastOrdersByUser(User $user): array;

    /**
     * Persists an Order entity to the database.
     *
     * This method should handle both new and existing entities.
     *
     * @param Order $order The Order entity to save.
     */
    public function save(Order $order): void;

    /**
     * Removes an Order entity from the database.
     *
     * @param Order $order The Order entity to remove.
     */
    public function remove(Order $order): void;

    /**
     * Flushes all pending changes to the database.
     *
     * This method commits all changes tracked by the Entity Manager to the database.
     */
    public function flush(): void;

    /**
     * Counts the number of Order entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @return int The number of entities matching the criteria.
     */
    public function count(array $criteria = []): int;

    /**
     * Finds Order entities by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses (e.g., ['startDate' => 'ASC']).
     * @param int|null $limit The maximum number of results to retrieve.
     * @param int|null $offset The offset from the beginning of the result set.
     * @return Order[] An array of Order entities.
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @param LockMode|int|null $lockMode The lock mode to apply to the entity.
     * @param int|null $lockVersion The entity's version.
     * @return object|null The entity found or null if not found.
     */
    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?object;
}