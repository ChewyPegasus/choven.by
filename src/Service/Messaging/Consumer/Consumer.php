<?php

declare(strict_types=1);

namespace App\Service\Messaging\Consumer;

use Interop\Queue\Message;

interface Consumer
{
    public function subscribe(string $topic): void;

    public function consume(int $timeout = 5000): ?Message;
}