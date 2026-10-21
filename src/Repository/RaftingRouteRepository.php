<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RaftingRoute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RaftingRoute>
 */
class RaftingRouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RaftingRoute::class);
    }

    /**
     * @return list<RaftingRoute>
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return list<RaftingRoute>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function getNextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('r')
            ->select('MAX(r.position)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(RaftingRoute $route, bool $flush = true): void
    {
        $this->getEntityManager()->persist($route);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RaftingRoute $route, bool $flush = true): void
    {
        $this->getEntityManager()->remove($route);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
