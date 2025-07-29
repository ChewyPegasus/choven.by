<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

interface FailedEmailRepositoryInterface
{
    public function save($failedEmail): void;

    public function remove($failedEmail): void;

    public function flush(): void;
}
