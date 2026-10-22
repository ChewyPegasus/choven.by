<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WaitlistEntry;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaitlistEntry>
 */
class WaitlistEntryRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaitlistEntry::class);
    }

    /**
     * Entries still waiting whose trip overlaps the period (oldest first: first come, first served).
     *
     * @return list<WaitlistEntry>
     */
    public function findWaitingOverlapping(\DateTimeImmutable $from, \DateTimeImmutable $to, \DateTimeImmutable $today): array
    {
        /* @var list<WaitlistEntry> */
        return $this->createQueryBuilder('w')
            ->andWhere('w.notifiedAt IS NULL')
            ->andWhere('w.startDate >= :today')
            // A trip of at most 7 days starting up to 6 days before $from can overlap the period.
            ->andWhere('w.startDate BETWEEN :earliest AND :to')
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->setParameter('earliest', $from->modify('-6 days'), Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->orderBy('w.createdAt', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function existsWaiting(string $email, \DateTimeImmutable $startDate): bool
    {
        return null !== $this->findOneBy(['email' => mb_strtolower($email), 'startDate' => $startDate, 'notifiedAt' => null]);
    }

    /**
     * @return Page<WaitlistEntry>
     */
    public function findUpcomingPage(\DateTimeImmutable $today, int $page): Page
    {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.tour', 't')->addSelect('t')
            ->andWhere('w.startDate >= :today')
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->orderBy('w.startDate', \SortDirection::Ascending)
            ->addOrderBy('w.createdAt', \SortDirection::Ascending);

        /* @var Page<WaitlistEntry> */
        return Page::fromQueryBuilder($qb, $page);
    }

    public function countWaiting(\DateTimeImmutable $today): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.notifiedAt IS NULL')
            ->andWhere('w.startDate >= :today')
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function removePast(\DateTimeImmutable $today): int
    {
        return (int) $this->createQueryBuilder('w')
            ->delete()
            ->andWhere('w.startDate < :today')
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->execute();
    }
}
