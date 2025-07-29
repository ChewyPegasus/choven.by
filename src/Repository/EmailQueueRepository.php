<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailQueue;
use App\Repository\Interfaces\EmailQueueRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmailQueueRepository extends ServiceEntityRepository implements EmailQueueRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailQueue::class);
    }

    /**
     * Find emails that need to be retried
     * Limit to emails that have been attempted less than MAX_ATTEMPTS times 
     */
    public function findEmailsToRetry(int $maxAttempts): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.attempts < :maxAttempts')
            ->setParameter('maxAttempts', $maxAttempts)
            ->getQuery()
            ->getResult();
    }

    public function findFailedEmails(int $maxAttempts): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.attempts >= :maxAttempts')
            ->setParameter('maxAttempts', $maxAttempts)
            ->getQuery()
            ->getResult();
    }

    public function save($emailQueue): void
    {
        $this->getEntityManager()->persist($emailQueue);
        $this->getEntityManager()->flush();
    }

    public function remove($emailQueue): void
    {
        $this->getEntityManager()->remove($emailQueue);
        $this->getEntityManager()->flush();
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
