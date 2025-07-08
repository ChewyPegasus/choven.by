<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findUpcomingOrdersByUser(User $user): array
    {
        $today = new \DateTime('today');
        
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.startDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->orderBy('o.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPastOrdersByUser(User $user): array
    {
        $today = new \DateTime('today');
        
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.startDate < :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->orderBy('o.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
