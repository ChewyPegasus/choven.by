<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Review;
use App\Enum\ReviewStatus;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return list<Review>
     */
    public function findPublished(int $limit = 9): array
    {
        /* @var list<Review> */
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :approved')
            ->setParameter('approved', ReviewStatus::APPROVED)
            ->orderBy('r.publishedAt', \SortDirection::Descending)
            ->addOrderBy('r.id', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public function getRatingSummary(): array
    {
        /** @var array{average: string|float|null, total: int|string} $row */
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS average, COUNT(r.id) AS total')
            ->andWhere('r.status = :approved')
            ->setParameter('approved', ReviewStatus::APPROVED)
            ->getQuery()
            ->getSingleResult();

        $count = (int) $row['total'];

        return [
            'average' => $count > 0 ? round((float) $row['average'], 1) : null,
            'count' => $count,
        ];
    }

    public function countPending(): int
    {
        return $this->count(['status' => ReviewStatus::PENDING]);
    }

    public function findOneByOrder(Order $order): ?Review
    {
        return $this->findOneBy(['order' => $order]);
    }

    /**
     * @return Page<Review>
     */
    public function findByStatus(?ReviewStatus $status, int $page, int $perPage = 20): Page
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.order', 'o')->addSelect('o')
            ->orderBy('r.createdAt', \SortDirection::Descending);

        if (null !== $status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        /* @var Page<Review> */
        return Page::fromQueryBuilder($qb, $page, $perPage);
    }

    public function save(Review $review, bool $flush = true): void
    {
        $this->getEntityManager()->persist($review);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Review $review, bool $flush = true): void
    {
        $this->getEntityManager()->remove($review);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
