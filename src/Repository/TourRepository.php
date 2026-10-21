<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tour>
 */
class TourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tour::class);
    }

    /**
     * @return list<Tour>
     */
    public function findActive(): array
    {
        /* @var list<Tour> */
        return $this->createQueryBuilder('t')
            ->leftJoin('t.route', 'r')->addSelect('r')
            ->andWhere('t.isActive = true')
            ->orderBy('t.position', \SortDirection::Ascending)
            ->addOrderBy('t.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Tour>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function findOneActiveBySlug(string $slug): ?Tour
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }

    public function getNextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Tour $tour, bool $flush = true): void
    {
        $this->getEntityManager()->persist($tour);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tour $tour, bool $flush = true): void
    {
        $this->getEntityManager()->remove($tour);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
