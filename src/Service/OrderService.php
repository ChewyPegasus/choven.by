<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;
use App\Factory\OrderFactory;

/**
 * Service for managing order-related operations.
 *
 * This service acts as an intermediary between controllers/other services
 * and the `OrderFactory`, providing a clean interface for creating `Order` entities.
 */
class OrderService 
{
    /**
     * Constructs a new OrderService instance.
     *
     * @param OrderFactory $orderFactory The factory responsible for creating Order entities.
     */
    public function __construct(private readonly OrderFactory $orderFactory,)
    { 
    }

    /**
     * Creates a new Order entity from an HTTP Request.
     *
     * This method delegates the creation of the `Order` entity to the `OrderFactory`,
     * passing the raw HTTP request data for population.
     *
     * @param Request $request The HTTP request containing order data (e.g., query parameters).
     * @return Order A new Order entity populated from the request.
     */
    public function create(Request $request): Order
    {
        return $this->orderFactory->create($request);
    }
}