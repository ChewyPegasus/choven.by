<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\Order\CreateOrderDTO;
use App\DTO\Order\FormOrderDTO;
use App\DTO\Order\OrderDTO;
use App\DTO\Order\UpdateOrderDTO;
use App\Entity\Order;
use App\Enum\Package;
use App\Enum\River;
use App\Exception\ValidationException;
use App\Repository\Interfaces\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Factory for creating and populating Order entities.
 */
class OrderFactory
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Creates a new Order entity from an OrderDTO and validates it.
     *
     * @param OrderDTO $dto The OrderDTO containing the order data.
     * @return Order The created and validated Order entity.
     * @throws ValidationException If validation fails.
     */
    public function createFromDTO(OrderDTO $dto): Order
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

        if ($errors->count() > 0) {
            throw new ValidationException($errors);
        }

        return $order;
    }

    /**
     * Creates a new Order entity from a FormOrderDTO and validates it.
     *
     * @param FormOrderDTO $dto The FormOrderDTO containing the order data from a form.
     * @return Order The created and validated Order entity.
     * @throws ValidationException If validation fails.
     */
    public function createFromFormDTO(FormOrderDTO $dto): Order
    {
        $order = new Order();
        $order->setEmail($dto->email);
        $order->setStartDate($dto->startDate);
        $order->setRiver($dto->river);
        $order->setPackage($dto->package);
        $order->setAmountOfPeople($dto->amountOfPeople);
        $order->setDurationDays($dto->duration);
        $order->setDescription($dto->description);
        $order->setLocale($dto->locale);

        $errors = $this->validator->validate($order);

        if ($errors->count() > 0) {
            throw new ValidationException($errors);
        }

        return $order;
    }

    /**
     * Creates a new Order entity from a CreateOrderDTO and validates it.
     *
     * @param CreateOrderDTO $dto The CreateOrderDTO containing the order data.
     * @return Order The created and validated Order entity.
     * @throws ValidationException If validation fails.
     */
    public function createFromCreateDTO(CreateOrderDTO $dto): Order
    {
        $order = new Order();
        $order->setEmail($dto->email);
        $order->setStartDate(new \DateTime($dto->startDate));
        $order->setRiver($dto->river);
        $order->setPackage($dto->package);
        $order->setAmountOfPeople($dto->amountOfPeople);
        $order->setDurationDays($dto->durationDays);
        $order->setDescription($dto->description);

        $errors = $this->validator->validate($order);

        if ($errors->count() > 0) {
            throw new ValidationException($errors);
        }

        return $order;
    }

    /**
     * Updates an existing order from UpdateOrderDTO.
     *
     * @param Order $order
     * @param UpdateOrderDTO $dto
     * @return Order The updated and validated Order entity.
     * @throws ValidationException If validation fails.
     */
    public function updateFromDTO(Order $order, UpdateOrderDTO $dto): Order
    {
        if ($dto->email !== null) {
            $order->setEmail($dto->email);
        }
        
        if ($dto->startDate !== null) {
            $order->setStartDate(new \DateTime($dto->startDate));
        }
        
        if ($dto->river !== null) {
            $order->setRiver($dto->river);
        }
        
        if ($dto->package !== null) {
            $order->setPackage($dto->package);
        }
        
        if ($dto->amountOfPeople !== null) {
            $order->setAmountOfPeople($dto->amountOfPeople);
        }
        
        if ($dto->durationDays !== null) {
            $order->setDurationDays($dto->durationDays);
        }
        
        if ($dto->description !== null) {
            $order->setDescription($dto->description);
        }

        $errors = $this->validator->validate($order);

        if ($errors->count() > 0) {
            throw new ValidationException($errors);
        }

        return $order;
    }

    /**
     * Creates a new Order entity and populates it from an HTTP Request.
     */
    public function create(Request $request): Order
    {
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
}