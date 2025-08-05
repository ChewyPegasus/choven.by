<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\Order\FormOrderDTO;
use App\DTO\Order\OrderDTO;
use App\DTO\Order\UpdateOrderDTO;
use App\Entity\Order;
use App\Enum\Package;
use App\Enum\River;
use App\Repository\Interfaces\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Factory for creating and populating Order entities.
 *
 * This factory provides methods to instantiate `Order` objects from
 * various sources, such as HTTP requests or Data Transfer Objects (DTOs),
 * and handles initial data population and validation.
 */
class OrderFactory
{
    /**
     * Constructs a new OrderFactory instance.
     *
     * @param OrderRepositoryInterface $orderRepository The repository for managing Order entities.
     * @param ValidatorInterface $validator The Symfony validator service for validating Order entities.
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Creates a new Order entity and populates it from an HTTP Request.
     *
     * This method extracts 'type', 'duration', and 'river' query parameters
     * from the request and sets them on a new `Order` entity. It attempts
     * to convert 'type' and 'river' into their corresponding Enum cases.
     *
     * @param Request $request The HTTP request from which to extract data.
     * @return Order A new Order entity populated with request data.
     */
    public function create(Request $request): Order
    {
        $order = new Order();

        // Retrieve query parameters
        $type = $request->query->get('type');
        $duration = $request->query->get('duration');
        $river = $request->query->get('river');

        // Set properties if parameters are present
        if ($type) {
            $order->setPackage(Package::from($type));
        }
        if ($river) {
            $order->setRiver(River::from($river));
        }
        if ($duration) {
            // Create a DateInterval from the duration in days
            $order->setDuration(new \DateInterval('P' . $duration . 'D'));
        }

        return $order;
    }

    /**
     * Creates a new Order entity from an OrderDTO and validates it.
     *
     * This method takes an `OrderDTO` (typically used for API input) and
     * transfers its data to a new `Order` entity. It then validates the
     * populated `Order` entity using the Symfony Validator.
     *
     * @param OrderDTO $dto The OrderDTO containing the order data.
     * @return array An array containing the created Order entity and a ConstraintViolationList of errors.
     */
    public function createFromDTO(OrderDTO $dto): array
    {
        $order = new Order();
        $order->setEmail($dto->getEmail());
        $order->setStartDate($dto->getStartDate());
        $order->setRiver($dto->getRiver());
        $order->setPackage($dto->getPackage());
        $order->setAmountOfPeople($dto->getAmountOfPeople());
        $order->setDurationDays($dto->getDurationDays()); // Assuming getDurationDays returns int
        $order->setDescription($dto->getDescription());

        $errors = $this->validator->validate($order);

        return [$order, $errors];
    }

    /**
     * Creates a new Order entity from a FormOrderDTO and validates it.
     *
     * This method takes a `FormOrderDTO` (typically used for web form input) and
     * transfers its data to a new `Order` entity. It then validates the
     * populated `Order` entity using the Symfony Validator.
     *
     * @param FormOrderDTO $dto The FormOrderDTO containing the order data from a form.
     * @return array An array containing the created Order entity and a ConstraintViolationList of errors.
     */
    public function createFromFormDTO(FormOrderDTO $dto): array
    {
        $order = new Order();
        $order->setEmail($dto->email);
        $order->setStartDate($dto->startDate);
        $order->setRiver($dto->river);
        $order->setPackage($dto->package);
        $order->setAmountOfPeople($dto->amountOfPeople);
        $order->setDurationDays($dto->duration); // Assuming duration property in DTO is int (days)
        $order->setDescription($dto->description);
        $order->setLocale($dto->locale);

        $errors = $this->validator->validate($order);

        return [$order, $errors];
    }

    /**
     * Updates an existing order from UpdateOrderDTO.
     *
     * @param Order $order
     * @param UpdateOrderDTO $dto
     * @return array{Order, ConstraintViolationListInterface}
     */
    public function updateFromDTO(Order $order, UpdateOrderDTO $dto): array
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

        return [$order, $errors];
    }
}