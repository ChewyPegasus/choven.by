<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\DTO;

interface Sender {
    function send(DTO $dto): void;
}