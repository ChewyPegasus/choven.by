<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GiftCertificate;
use App\Enum\GiftCertificateStatus;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GiftCertificate>
 */
class GiftCertificateRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GiftCertificate::class);
    }

    public function findOneByCode(string $code): ?GiftCertificate
    {
        return $this->findOneBy(['code' => mb_strtoupper(trim($code))]);
    }

    public function findOneByToken(string $token): ?GiftCertificate
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * @return Page<GiftCertificate>
     */
    public function findPage(?GiftCertificateStatus $status, ?string $search, int $page): Page
    {
        $qb = $this->createQueryBuilder('g')->orderBy('g.createdAt', \SortDirection::Descending);
        if (null !== $status) {
            $qb->andWhere('g.status = :status')->setParameter('status', $status);
        }
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('LOWER(g.code) LIKE :q OR LOWER(g.buyerEmail) LIKE :q OR LOWER(g.buyerName) LIKE :q OR LOWER(g.recipientName) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($search)).'%');
        }

        /* @var Page<GiftCertificate> */
        return Page::fromQueryBuilder($qb, $page);
    }

    /**
     * Unpaid certificates abandoned at the payment step.
     */
    public function removePendingOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('g')
            ->delete()
            ->andWhere('g.status = :pending')
            ->andWhere('g.createdAt < :before')
            ->setParameter('pending', GiftCertificateStatus::PENDING)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
