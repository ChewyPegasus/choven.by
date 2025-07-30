<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\Interfaces\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\DBAL\LockMode; // Import LockMode for type hinting

/**
 * Repository for managing User entities.
 *
 * This class provides data access methods for `User` entities,
 * including password upgrading, finding users by roles, searching,
 * and standard persistence operations.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserRepositoryInterface
{
    /**
     * Constructs a new UserRepository.
     *
     * @param ManagerRegistry $registry The Doctrine ManagerRegistry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     *
     * @param PasswordAuthenticatedUserInterface $user The user object whose password needs upgrading.
     * @param string $newHashedPassword The new hashed password.
     * @throws UnsupportedUserException If the user is not an instance of `App\Entity\User`.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Finds all users that have a specific role.
     *
     * This method retrieves all users and then filters them in PHP to check
     * for the presence of the specified role, handling both enum and string representations.
     *
     * @param Role $role The Role enum to search for.
     * @return User[] An array of User entities having the specified role, ordered by email.
     */
    public function findByRole(Role $role): array
    {
        $allUsers = $this->findBy([], ['email' => 'ASC']);
        
        return array_filter($allUsers, function(User $user) use ($role) {
            // Special handling for ADMIN role if isAdmin() has custom logic
            if ($role === Role::ADMIN) {
                return $user->isAdmin();
            }
            
            // Check if the user has the role (enum comparison)
            // Or if the string representation of the role is present in getRoles()
            return $user->hasRole($role) || in_array('ROLE_' . strtoupper($role->value), $user->getRoles(), true);
        });
    }

    /**
     * Counts the number of users that have a specific role.
     *
     * This method leverages `findByRole` and counts the results.
     *
     * @param Role $role The Role enum to count.
     * @return int The number of users with the specified role.
     */
    public function countByRole(Role $role): int
    {
        return count($this->findByRole($role));
    }

    /**
     * Finds all users that do NOT have a specific role.
     *
     * This method retrieves all users and then filters them in PHP
     * to exclude those with the specified role.
     *
     * @param Role $role The Role enum to exclude.
     * @return User[] An array of User entities without the specified role, ordered by email.
     */
    public function findUsersWithoutRole(Role $role): array
    {
        $allUsers = $this->findBy([], ['email' => 'ASC']);
        
        return array_filter($allUsers, function(User $user) use ($role) {
            return !$user->hasRole($role);
        });
    }

    /**
     * Searches for users whose email or phone number matches a given query string.
     *
     * The search is performed using a LIKE query on both email and phone fields.
     * Results are limited by `$limit` and ordered by email.
     *
     * @param string $query The search query string.
     * @param int $limit The maximum number of results to return. Defaults to 10.
     * @return User[] An array of User entities matching the search query.
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :query OR u.phone LIKE :query') // Assuming phone can be searched as string
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Persists a User entity to the database.
     *
     * @param User $user The User entity to save.
     */
    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Removes a User entity from the database.
     *
     * @param User $user The User entity to remove.
     */
    public function remove(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Flushes all pending changes to the database.
     *
     * This method commits all changes tracked by the Entity Manager to the database.
     */
    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses.
     * @return object|null The entity found or null if not found.
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    /**
     * Finds all entities in the repository.
     *
     * @return array<User> An array of User entities.
     */
    public function findAll(): array
    {
        return parent::findAll();
    }

    /**
     * Counts the number of User entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @return int The number of entities matching the criteria.
     */
    public function count(array $criteria = []): int
    {
        return parent::count($criteria);
    }

    /**
     * Finds User entities by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses.
     * @param int|null $limit The maximum number of results to retrieve.
     * @param int|null $offset The offset from the beginning of the result set.
     * @return User[] An array of User entities.
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @param LockMode|int|null $lockMode The lock mode to apply to the entity.
     * @param int|null $lockVersion The entity's version.
     * @return object|null The entity found or null if not found.
     */
    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?object
    {
        return parent::find($id, $lockMode, $lockVersion);
    }
}