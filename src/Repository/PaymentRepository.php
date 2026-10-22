<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findOneByPublicId(string $publicId): ?Payment
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    /**
     * @return Page<Payment>
     */
    public function findPage(?PaymentStatus $status, int $page): Page
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.order', 'o')->addSelect('o')
            ->leftJoin('p.giftCertificate', 'g')->addSelect('g')
            ->orderBy('p.createdAt', \SortDirection::Descending);
        if (null !== $status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        /* @var Page<Payment> */
        return Page::fromQueryBuilder($qb, $page, fetchJoin: true);
    }

    /**
     * Sum of successful payments (minus refunds) since the given moment, for the dashboard.
     */
    public function sumReceivedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount - p.refundedAmount), 0)')
            ->andWhere('p.status IN (:statuses)')
            ->andWhere('p.paidAt >= :since')
            ->andWhere('p.test = false')
            ->setParameter('statuses', [PaymentStatus::SUCCEEDED, PaymentStatus::REFUNDED])
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Pending payments older than the given moment (abandoned checkouts).
     *
     * @return list<Payment>
     */
    public function findStalePending(\DateTimeImmutable $before): array
    {
        /* @var list<Payment> */
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :pending')
            ->andWhere('p.createdAt < :before')
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
