<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\OrderCreateDTO;
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

    public function createFromDTO(OrderCreateDTO $dto): array
    {
        $order = new Order();
        $order->setEmail($dto->email);
        $order->setStartDate(new \DateTime($dto->startDate));
        $order->setRiver(River::tryFrom($dto->river));
        $order->setPackage(Package::tryFrom($dto->package));
        $order->setAmountOfPeople($dto->amountOfPeople);
        $order->setDurationDays($dto->durationDays);
        $order->setDescription($dto->description);

        $errors = $this->validator->validate($order);

        return [$order, $errors];
    }
}