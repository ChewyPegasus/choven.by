<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Exception\OrderNotFoundException;
use App\Factory\ExceptionFactory;
use App\Repository\Interfaces\OrderRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Doctrine\DBAL\LockMode; // Import LockMode for type hinting

/**
 * Repository for managing Order entities.
 *
 * This class provides data access methods for `Order` entities,
 * including finding upcoming and past orders for a user, and standard
 * persistence operations.
 *
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    /**
     * Constructs a new OrderRepository.
     *
     * @param ManagerRegistry $registry The Doctrine ManagerRegistry.
     * @param ClockInterface $clock The PSR-20 ClockInterface for obtaining the current time.
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ClockInterface $clock,
        private readonly ExceptionFactory $exceptionFactory,
    ) {
        parent::__construct($registry, Order::class);
    }

    /**
     * Finds all upcoming orders for a given user.
     *
     * An "upcoming" order is defined as an order whose `startDate` is
     * on or after the current date (midnight of today).
     *
     * @param User $user The user for whom to find upcoming orders.
     * @return Order[] An array of upcoming Order entities, ordered by start date ascending.
     */
    public function findUpcomingOrdersByUser(User $user): array
    {
        // Get the current date at midnight to compare against order start dates
        $today = $this->clock->now()->setTime(0, 0, 0);
        
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.startDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->orderBy('o.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds all past orders for a given user.
     *
     * A "past" order is defined as an order whose `startDate` is
     * before the current date (midnight of today).
     *
     * @param User $user The user for whom to find past orders.
     * @return Order[] An array of past Order entities, ordered by start date descending.
     */
    public function findPastOrdersByUser(User $user): array
    {
        // Get the current date at midnight to compare against order start dates
        $today = $this->clock->now()->setTime(0, 0, 0);
        
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.startDate < :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->orderBy('o.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Persists an Order entity to the database.
     *
     * @param Order $order The Order entity to save.
     */
    public function save(Order $order): void
    {
        $this->getEntityManager()->persist($order);
        $this->getEntityManager()->flush();
    }

    /**
     * Removes an Order entity from the database.
     *
     * @param Order $order The Order entity to remove.
     */
    public function remove(Order $order): void
    {
        $this->getEntityManager()->remove($order);
        $this->getEntityManager()->flush();
    }

    /**
     * Flushes all pending changes to the database.
     *
     * This method commits all changes tracked by the Entity Manager to the database.
     */
    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * Counts the number of Order entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @return int The number of entities matching the criteria.
     */
    public function count(array $criteria = []): int
    {
        return parent::count($criteria);
    }

    /**
     * Finds Order entities by a set of criteria.
     *
     * @param array<string, mixed> $criteria An array of key-value pairs to match.
     * @param array<string, string>|null $orderBy An array of order by clauses.
     * @param int|null $limit The maximum number of results to retrieve.
     * @param int|null $offset The offset from the beginning of the result set.
     * @return Order[] An array of Order entities.
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @param LockMode|int|null $lockMode The lock mode to apply to the entity.
     * @param int|null $lockVersion The entity's version.
     * @return object|null The entity found or null if not found.
     */
    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?object
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    /**
     * @throws OrderNotFoundException If no order with the given ID exists.
     */
    public function getById(int $id): Order
    {
        $order = $this->find($id);

        if (!$order) {
            throw $this->exceptionFactory->createOrderNotFoundException($id);
        }

        return $order;
    }

    /**
     * Finds orders with pagination support, ordered by start date descending.
     */
    public function findWithPagination(int $limit, int $offset): array
    {
        return $this->findBy(
            [],
            ['startDate' => 'DESC'],
            $limit,
            $offset
        );
    }
}