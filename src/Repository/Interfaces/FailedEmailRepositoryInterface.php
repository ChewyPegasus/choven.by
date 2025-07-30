<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

interface FailedEmailRepositoryInterface
{
    public function save($failedEmail): void;

    public function remove($failedEmail): void;

    public function flush(): void;

    public function count(array $criteria = []): int;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
}
