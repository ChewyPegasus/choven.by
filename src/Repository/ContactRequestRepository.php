<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactRequest;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactRequest>
 */
class ContactRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactRequest::class);
    }

    public function countUnprocessed(): int
    {
        return $this->count(['isProcessed' => false]);
    }

    /**
     * @return Page<ContactRequest>
     */
    public function findPage(?bool $processed, int $page, int $perPage = 25): Page
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.isProcessed', \SortDirection::Ascending)
            ->addOrderBy('c.createdAt', \SortDirection::Descending);

        if (null !== $processed) {
            $qb->andWhere('c.isProcessed = :processed')->setParameter('processed', $processed);
        }

        /* @var Page<ContactRequest> */
        return Page::fromQueryBuilder($qb, $page, $perPage);
    }

    public function save(ContactRequest $request, bool $flush = true): void
    {
        $this->getEntityManager()->persist($request);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContactRequest $request, bool $flush = true): void
    {
        $this->getEntityManager()->remove($request);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
