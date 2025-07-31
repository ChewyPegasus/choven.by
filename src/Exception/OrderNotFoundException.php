<?php

declare(strict_types=1);

namespace App\Exception;

class OrderNotFoundException extends \RuntimeException
{
    protected $message;

    public function __construct(
        public int $id,
    )
    {
        $this->message = sprintf('Order with ID %d not found.', $id);
    }
}