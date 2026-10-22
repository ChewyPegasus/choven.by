<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @return Page<AuditLog>
     */
    public function findPage(?string $entityType, ?string $entityId, ?string $user, int $page): Page
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', \SortDirection::Descending)
            ->addOrderBy('a.id', \SortDirection::Descending);
        if (null !== $entityType && '' !== $entityType) {
            $qb->andWhere('a.entityType = :type')->setParameter('type', $entityType);
        }
        if (null !== $entityId && '' !== $entityId) {
            $qb->andWhere('a.entityId = :id')->setParameter('id', $entityId);
        }
        if (null !== $user && '' !== $user) {
            $qb->andWhere('LOWER(a.userEmail) LIKE :user')->setParameter('user', '%'.mb_strtolower($user).'%');
        }

        /* @var Page<AuditLog> */
        return Page::fromQueryBuilder($qb, $page, 50);
    }

    /**
     * @return list<string>
     */
    public function findEntityTypes(): array
    {
        /** @var list<string> */
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->andWhere('a.entityType IS NOT NULL')
            ->orderBy('a.entityType')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function removeOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
