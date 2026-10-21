<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Model\OrderFilter;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, Order::class);
    }

    public function findOneByAccessToken(string $token): ?Order
    {
        return $this->findOneBy(['accessToken' => $token]);
    }

    public function findOneByReference(string $reference): ?Order
    {
        return $this->findOneBy(['reference' => mb_strtoupper(trim($reference))]);
    }

    /**
     * @return Page<Order>
     */
    public function findByFilter(OrderFilter $filter, int $page, int $perPage = 25): Page
    {
        /* @var Page<Order> */
        return Page::fromQueryBuilder($this->createFilterQueryBuilder($filter), $page, $perPage);
    }

    /**
     * Same as findByFilter but without pagination, as a generator — used by the CSV export.
     *
     * @return iterable<Order>
     */
    public function iterateByFilter(OrderFilter $filter): iterable
    {
        /* @var iterable<Order> */
        return $this->createFilterQueryBuilder($filter)->getQuery()->toIterable();
    }

    private function createFilterQueryBuilder(OrderFilter $filter): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.tour', 't')->addSelect('t')
            ->leftJoin('o.user', 'u')->addSelect('u');

        if (null !== $filter->query && '' !== trim($filter->query)) {
            $q = mb_strtolower(trim($filter->query));
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            $or = 'LOWER(o.reference) LIKE :q OR LOWER(o.name) LIKE :q OR LOWER(o.email) LIKE :q';
            if (\strlen($digits) >= 3) {
                $or .= ' OR o.phone LIKE :digits';
                $qb->setParameter('digits', '%'.$digits.'%');
            }
            $qb->andWhere($or)->setParameter('q', '%'.$q.'%');
        }
        if (null !== $filter->status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $filter->status);
        }
        if (null !== $filter->river) {
            $qb->andWhere('o.river = :river')->setParameter('river', $filter->river);
        }
        if (null !== $filter->package) {
            $qb->andWhere('o.package = :package')->setParameter('package', $filter->package);
        }
        if (null !== $filter->from) {
            $qb->andWhere('o.startDate >= :from')->setParameter('from', $filter->from->setTime(0, 0), Types::DATE_IMMUTABLE);
        }
        if (null !== $filter->to) {
            $qb->andWhere('o.startDate <= :to')->setParameter('to', $filter->to->setTime(0, 0), Types::DATE_IMMUTABLE);
        }

        match ($filter->sort) {
            'start_asc' => $qb->orderBy('o.startDate', \SortDirection::Ascending),
            'created_desc' => $qb->orderBy('o.createdAt', \SortDirection::Descending),
            'created_asc' => $qb->orderBy('o.createdAt', \SortDirection::Ascending),
            default => $qb->orderBy('o.startDate', \SortDirection::Descending),
        };
        $qb->addOrderBy('o.id', \SortDirection::Descending);

        return $qb;
    }

    /**
     * @return list<Order>
     */
    public function findUpcomingForUser(User $user): array
    {
        /* @var list<Order> */
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->andWhere('o.startDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', $this->today(), Types::DATE_IMMUTABLE)
            ->orderBy('o.startDate', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Order>
     */
    public function findPastForUser(User $user): array
    {
        /* @var list<Order> */
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->andWhere('o.startDate < :today')
            ->setParameter('user', $user)
            ->setParameter('today', $this->today(), Types::DATE_IMMUTABLE)
            ->orderBy('o.startDate', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Links guest bookings made with the user's email to the account (optionally only those created since $since).
     */
    public function attachGuestOrdersToUser(User $user, ?\DateTimeImmutable $since = null): int
    {
        $qb = $this->createQueryBuilder('o')
            ->update()
            ->set('o.user', ':user')
            ->andWhere('o.user IS NULL')
            ->andWhere('o.email = :email')
            ->setParameter('user', $user)
            ->setParameter('email', $user->getEmail());

        if (null !== $since) {
            $qb->andWhere('o.createdAt > :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return list<Order>
     */
    public function findUpcoming(int $limit = 10): array
    {
        /* @var list<Order> */
        return $this->createQueryBuilder('o')
            ->andWhere('o.startDate >= :today')
            ->andWhere('o.status IN (:active)')
            ->setParameter('today', $this->today(), Types::DATE_IMMUTABLE)
            ->setParameter('active', OrderStatus::active())
            ->orderBy('o.startDate', \SortDirection::Ascending)
            ->addOrderBy('o.id', \SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Bookings that touch the [from, to] period (a trip can span several days); active ones by default.
     *
     * @param list<OrderStatus>|null $statuses
     *
     * @return list<Order>
     */
    public function findActiveInPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Order $exclude = null, ?array $statuses = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.status IN (:active)')
            ->andWhere('o.startDate >= :earliest')
            ->andWhere('o.startDate <= :to')
            ->setParameter('active', $statuses ?? OrderStatus::active())
            // Trips last at most MAX_DAYS, so anything that started earlier has already ended.
            ->setParameter('earliest', $from->setTime(0, 0)->modify(\sprintf('-%d days', Order::MAX_DAYS - 1)), Types::DATE_IMMUTABLE)
            ->setParameter('to', $to->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('o.startDate', \SortDirection::Ascending);

        if (null !== $exclude?->getId()) {
            $qb->andWhere('o.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        /** @var list<Order> $orders */
        $orders = $qb->getQuery()->getResult();
        $from = $from->setTime(0, 0);

        return array_values(array_filter($orders, static fn (Order $o): bool => $o->getEndDate() >= $from));
    }

    /**
     * Number of booked people per day ("Y-m-d" => people) for the capacity check and the calendar.
     *
     * @return array<string, int>
     */
    public function getBookedPeopleByDay(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Order $exclude = null): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        $result = [];

        foreach ($this->findActiveInPeriod($from, $to, $exclude) as $order) {
            $day = $order->getStartDate();
            $end = $order->getEndDate();
            while (null !== $day && $day <= $end) {
                if ($day >= $from && $day <= $to) {
                    $key = $day->format('Y-m-d');
                    $result[$key] = ($result[$key] ?? 0) + (int) $order->getAmountOfPeople();
                }
                $day = $day->modify('+1 day');
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * @return array<string, int> status value => count
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status AS status, COUNT(o.id) AS total')
            ->groupBy('o.status')
            ->getQuery()
            ->getArrayResult();

        $result = array_fill_keys(array_map(static fn (OrderStatus $s): string => $s->value, OrderStatus::cases()), 0);
        foreach ($rows as $row) {
            $status = $row['status'] instanceof OrderStatus ? $row['status']->value : (string) $row['status'];
            $result[$status] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * New bookings that still need a call: trips in the past are not counted.
     */
    public function countNewUpcoming(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :new')
            ->andWhere('o.startDate >= :today')
            ->setParameter('new', OrderStatus::NEW)
            ->setParameter('today', $this->today(), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Bookings and people per month of the trip start date: $past months back and $future months ahead.
     *
     * @return array<string, array{orders: int, people: int}> "Y-m" => totals, oldest first
     */
    public function getMonthlyStats(int $past = 8, int $future = 3): array
    {
        $thisMonth = $this->today()->modify('first day of this month');
        $start = $thisMonth->modify(\sprintf('-%d months', $past));
        $end = $thisMonth->modify(\sprintf('+%d months', $future))->modify('last day of this month');

        $result = [];
        for ($month = $start; $month <= $end; $month = $month->modify('+1 month')) {
            $result[$month->format('Y-m')] = ['orders' => 0, 'people' => 0];
        }

        $rows = $this->createQueryBuilder('o')
            ->select('o.startDate AS startDate, o.amountOfPeople AS people')
            ->andWhere('o.startDate BETWEEN :start AND :end')
            ->andWhere('o.status != :cancelled')
            ->setParameter('start', $start, Types::DATE_IMMUTABLE)
            ->setParameter('end', $end, Types::DATE_IMMUTABLE)
            ->setParameter('cancelled', OrderStatus::CANCELLED)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $key = $row['startDate']->format('Y-m');
            if (isset($result[$key])) {
                ++$result[$key]['orders'];
                $result[$key]['people'] += (int) $row['people'];
            }
        }

        return $result;
    }

    public function save(Order $order, bool $flush = true): void
    {
        $this->getEntityManager()->persist($order);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $order, bool $flush = true): void
    {
        $this->getEntityManager()->remove($order);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }
}
