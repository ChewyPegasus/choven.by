<?php

declare(strict_types=1);

namespace App\Exception;

class UserNotFoundException extends \RuntimeException
{
    protected $message;

    public function __construct(
        public int $identifier,
    )
    {
        $this->message = sprintf('User with identifier %d not found.', $identifier);
    }
}