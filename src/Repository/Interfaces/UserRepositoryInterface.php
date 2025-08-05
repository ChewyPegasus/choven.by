<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Doctrine\DBAL\LockMode; // Import LockMode for type hinting

/**
 * Interface for the UserRepository.
 *
 * Defines the contract for a repository that manages `User` entities,
 * providing methods for password upgrades, finding users by roles,
 * searching, and standard persistence operations.
 */
interface UserRepositoryInterface
{
    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     *
     * @param PasswordAuthenticatedUserInterface $user The user object whose password needs upgrading.
     * @param string $newHashedPassword The new hashed password.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void;

    /**
     * Finds all users that have a specific role.
     *
     * @param Role $role The Role enum to search for.
     * @return User[] An array of User entities having the specified role.
     */
    public function findByRole(Role $role): array;

    /**
     * Counts the number of users that have a specific role.
     *
     * @param Role $role The Role enum to count.
     * @return int The number of users with the specified role.
     */
    public function countByRole(Role $role): int;

    /**
     * Finds all users that do NOT have a specific role.
     *
     * @param Role $role The Role enum to exclude.
     * @return User[] An array of User entities without the specified role.
     */
    public function findUsersWithoutRole(Role $role): array;

    /**
     * Searches for users whose email or phone number matches a given query string.
     *
     * @param string $query The search query string.
     * @param int $limit The maximum number of results to return. Defaults to 10.
     * @return User[] An array of User entities matching the search query.
     */
    public function searchUsers(string $query, int $limit = 10): array;

    /**
     * Persists a User entity to the database.
     *
     * This method should handle both new and existing entities.
     *
     * @param User $user The User entity to save.
     */
    public function save(User $user): void;

    /**
     * Removes a User entity from the database.
     *
     * @param User $user The User entity to remove.
     */
    public function remove(User $user): void;

    /**
     * Flushes all pending changes to the database.
     *
     * This method commits all changes tracked by the Entity Manager to the database.
     */
    public function flush(): void;

    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses.
     * @return object|null The entity found or null if not found.
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    /**
     * Finds all entities in the repository.
     *
     * @return array<User> An array of User entities.
     */
    public function findAll(): array;

    /**
     * Counts the number of User entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @return int The number of entities matching the criteria.
     */
    public function count(array $criteria = []): int;

    /**
     * Finds User entities by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses.
     * @param int|null $limit The maximum number of results to retrieve.
     * @param int|null $offset The offset from the beginning of the result set.
     * @return User[] An array of User entities.
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

    public function findOneByEmail(string $email, ?array $orderBy = null): ?User;

    public function findOneByConfirmationCode(string $code, ?array $orderBy = null): ?User;

    public function findOneByPhone(string $phone, ?array $orderBy = null): ?User;
}