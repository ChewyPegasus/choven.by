<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailQueue;
use App\Repository\Interfaces\EmailQueueRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for managing EmailQueue entities.
 *
 * This class provides data access methods for `EmailQueue` entities,
 * allowing for retrieval of emails based on retry status and standard
 * persistence operations.
 *
 * @extends ServiceEntityRepository<EmailQueue>
 */
class EmailQueueRepository extends ServiceEntityRepository implements EmailQueueRepositoryInterface
{
    /**
     * Constructs a new EmailQueueRepository.
     *
     * @param ManagerRegistry $registry The Doctrine ManagerRegistry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailQueue::class);
    }

    /**
     * Finds emails that need to be retried.
     *
     * This method retrieves `EmailQueue` entities that have been attempted
     * fewer times than the specified `$maxAttempts`.
     *
     * @param int $maxAttempts The maximum number of attempts an email can have before it's considered failed.
     * @return EmailQueue[] An array of EmailQueue entities eligible for retry.
     */
    public function findEmailsToRetry(int $maxAttempts): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.attempts < :maxAttempts')
            ->setParameter('maxAttempts', $maxAttempts)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds emails that are considered permanently failed.
     *
     * This method retrieves `EmailQueue` entities that have reached or exceeded
     * the specified `$maxAttempts` threshold.
     *
     * @param int $maxAttempts The maximum number of attempts allowed for an email.
     * @return EmailQueue[] An array of EmailQueue entities that have failed.
     */
    public function findFailedEmails(int $maxAttempts): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.attempts >= :maxAttempts')
            ->setParameter('maxAttempts', $maxAttempts)
            ->getQuery()
            ->getResult();
    }

    /**
     * Persists an EmailQueue entity to the database.
     *
     * @param EmailQueue $emailQueue The EmailQueue entity to save.
     */
    public function save(EmailQueue $emailQueue): void
    {
        $this->getEntityManager()->persist($emailQueue);
        $this->getEntityManager()->flush();
    }

    /**
     * Removes an EmailQueue entity from the database.
     *
     * @param EmailQueue $emailQueue The EmailQueue entity to remove.
     */
    public function remove(EmailQueue $emailQueue): void
    {
        $this->getEntityManager()->remove($emailQueue);
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
}