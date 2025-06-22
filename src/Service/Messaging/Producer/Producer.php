<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

interface Producer
{
    public function publish(string $topic, string $message, ?string $key = null);
}