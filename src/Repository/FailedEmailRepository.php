<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FailedEmail;
use App\Repository\Interfaces\FailedEmailRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for managing FailedEmail entities.
 *
 * This class provides data access methods for `FailedEmail` entities,
 * offering standard persistence operations.
 *
 * @extends ServiceEntityRepository<FailedEmail>
 */
class FailedEmailRepository extends ServiceEntityRepository implements FailedEmailRepositoryInterface
{
    /**
     * Constructs a new FailedEmailRepository.
     *
     * @param ManagerRegistry $registry The Doctrine ManagerRegistry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FailedEmail::class);
    }

    /**
     * Persists a FailedEmail entity to the database.
     *
     * This method handles both new and existing entities.
     *
     * @param FailedEmail $failedEmail The FailedEmail entity to save.
     */
    public function save(FailedEmail $failedEmail): void
    {
        $this->getEntityManager()->persist($failedEmail);
        $this->getEntityManager()->flush();
    }

    /**
     * Removes a FailedEmail entity from the database.
     *
     * @param FailedEmail $failedEmail The FailedEmail entity to remove.
     */
    public function remove(FailedEmail $failedEmail): void
    {
        $this->getEntityManager()->remove($failedEmail);
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
     * Counts the number of FailedEmail entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @return int The number of entities matching the criteria.
     */
    public function count(array $criteria = []): int
    {
        return parent::count($criteria);
    }

    /**
     * Finds FailedEmail entities by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses (e.g., ['createdAt' => 'ASC']).
     * @param int|null $limit The maximum number of results to retrieve.
     * @param int|null $offset The offset from the beginning of the result set.
     * @return FailedEmail[] An array of FailedEmail entities.
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }
}