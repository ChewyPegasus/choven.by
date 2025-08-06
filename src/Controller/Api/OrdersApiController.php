<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ApiResponse\OrderApiResponseDTO;
use App\DTO\Order\CreateOrderDTO;
use App\DTO\Order\UpdateOrderDTO;
use App\Entity\Order;
use App\Exception\ValidationException;
use App\Exception\ValidationHttpException;
use App\Factory\OrderFactory;
use App\Repository\Interfaces\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * API controller for managing orders, accessible by administrators.
 */
#[Route('/api/admin/orders')]
#[IsGranted('ROLE_ADMIN')]
class OrdersApiController extends AbstractController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Retrieves a single order by its ID.
     */
    #[Route('/{id}', name: 'app_admin_api_orders_get', methods: ['GET'])]
    public function getOrderById(Order $order): JsonResponse
    {
        // Создаем массив данных заказа
        $orderData = [
            'id' => $order->getId(),
            'email' => $order->getEmail(),
            'startDate' => $order->getStartDate()->format('c'),
            'river' => $this->translator->trans('river.' . $order->getRiver()->value),
            'package' => $this->translator->trans($order->getPackage()->getLabel()),
            'amountOfPeople' => $order->getAmountOfPeople(),
            'durationDays' => $order->getDurationDays(),
            'description' => $order->getDescription(),
            'riverValue' => $order->getRiver()->value,
            'packageValue' => $order->getPackage()->value,
        ];
        
        $response = OrderApiResponseDTO::successWithOrder('Order retrieved successfully', $orderData);
        return $this->json($response->toArray());
    }

    /**
     * Deletes an order by its ID.
     */
    #[Route('/{id}', name: 'app_admin_api_orders_delete', methods: ['DELETE'])]
    public function deleteOrder(Order $order): JsonResponse
    {
        $this->orderRepository->remove($order);

        $response = OrderApiResponseDTO::success('Order deleted successfully');
        return $this->json($response->toArray());
    }

    /**
     * Creates a new order from the request payload.
     */
    #[Route('/', name: 'app_admin_api_orders_create', methods: ['POST'])]
    public function createOrder(
        #[MapRequestPayload] CreateOrderDTO $dto,
        OrderFactory $orderFactory,
    ): JsonResponse {
        try {
            $order = $orderFactory->createFromCreateDTO($dto);
            $this->orderRepository->save($order);
            
            $response = OrderApiResponseDTO::success('Order created successfully');
            return $this->json($response->toArray(), Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            throw new ValidationHttpException($e->getViolations(), 'Validation failed');
        }
    }

    /**
     * Updates an existing order by its ID.
     */
    #[Route('/{id}', name: 'app_admin_api_orders_update', methods: ['PUT'])]
    public function updateOrder(
        Order $order, 
        #[MapRequestPayload] UpdateOrderDTO $dto,
        OrderFactory $orderFactory
    ): JsonResponse {
        try {
            $updatedOrder = $orderFactory->updateFromDTO($order, $dto);
            $this->orderRepository->save($updatedOrder);
            
            $response = OrderApiResponseDTO::success('Order updated successfully');
            return $this->json($response->toArray());
        } catch (ValidationException $e) {
            throw new ValidationHttpException($e->getViolations(), 'Validation failed');
        }
    }
}