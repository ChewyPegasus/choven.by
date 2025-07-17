<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;
use App\Enum\River;
use App\Enum\Package;
use App\Factory\OrderFactory;

class OrderService 
{
    public function __construct(private readonly OrderFactory $orderFactory,)
    { 
    }

    public function create(Request $request): Order {
        return $this->orderFactory->create($request);
    }
}
