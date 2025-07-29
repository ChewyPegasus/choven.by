<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

use App\Entity\User;

interface OrderRepositoryInterface
{
    public function findUpcomingOrdersByUser(User $user): array;

    public function findPastOrdersByUser(User $user): array;

    public function save($order): void;

    public function remove($order): void;

    public function flush(): void;
}
