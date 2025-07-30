<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

use App\Entity\Order;
use App\Entity\User;

interface OrderRepositoryInterface
{
    public function findUpcomingOrdersByUser(User $user): array;

    public function findPastOrdersByUser(User $user): array;

    public function save($order): void;

    public function remove($order): void;

    public function flush(): void;

    public function count(array $criteria = []): int;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function find(mixed $id, \Doctrine\DBAL\LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?object;
}
