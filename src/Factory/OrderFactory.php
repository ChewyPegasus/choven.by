<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\OrderDTO;
use App\Entity\Order;
use App\Enum\Package;
use App\Enum\River;
use App\Repository\Interfaces\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrderFactory
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ValidatorInterface $validator,
    )
    {
    }

    public function create(Request $request): Order {
        $order = new Order();

        $type = $request->query->get('type');
        $duration = $request->query->get('duration');
        $river = $request->query->get('river');

        if ($type) {
            $order->setPackage(Package::from($type));
        }
        if ($river) {
            $order->setRiver(River::from($river));
        }
        if ($duration) {
            $order->setDuration(new \DateInterval('P' . $duration . 'D'));
        }

        return $order;
    }

    public function createFromDTO(OrderDTO $dto): array
    {
        $order = new Order();
        $order->setEmail($dto->getEmail());
        $order->setStartDate($dto->getStartDate());
        $order->setRiver($dto->getRiver());
        $order->setPackage($dto->getPackage());
        $order->setAmountOfPeople($dto->getAmountOfPeople());
        $order->setDurationDays($dto->getDurationDays());
        $order->setDescription($dto->getDescription());

        $errors = $this->validator->validate($order);

        return [$order, $errors];
    }
}