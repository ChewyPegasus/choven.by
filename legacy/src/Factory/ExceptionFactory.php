<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Exception\UserNotFoundException;

class ExceptionFactory
{
    public function createOrderNotFoundException($id) {
        return new OrderNotFoundException($id);
    }

    public function createUserNotFoundException($identifier) {
        return new UserNotFoundException($identifier);
    }
}