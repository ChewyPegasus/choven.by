<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

interface Producer
{
    public function produce(string $topic, string $message, ?string $key = null);
}