<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

use App\DTO\AbstractEmailDTO;

interface Producer
{
    public function produce(string $topic, AbstractEmailDTO $dto, ?string $key = null);
}