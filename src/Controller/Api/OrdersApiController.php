<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ApiResponse\OrderApiResponseDTO;
use App\DTO\Order\OrderDTO;
use App\DTO\Order\UpdateOrderDTO;
use App\Entity\Order;
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
 *
 * Provides endpoints for retrieving, creating, updating, and deleting order entities.
 * All actions in this controller require the authenticated user to have the 'ROLE_ADMIN' role.
 */
#[Route('/api/admin/orders')]
#[IsGranted('ROLE_ADMIN')]
class OrdersApiController extends AbstractController
{
    /**
     * Constructs a new OrdersApiController instance.
     *
     * @param OrderRepositoryInterface $orderRepository The repository for managing Order entities.
     * @param TranslatorInterface $translator The translator service for internationalization.
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Retrieves a single order by its ID.
     *
     * @param Order $order The Order entity resolved by the route parameter.
     * @return JsonResponse A JSON response containing the details of the order.
     */
    #[Route('/{id}', name: 'app_admin_api_orders_get', methods: ['GET'])]
    public function getOrderById(Order $order): JsonResponse
    {
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
     *
     * @param Order $order The Order entity resolved by the route parameter.
     * @return JsonResponse A JSON response indicating the success or failure of the deletion.
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
     *
     * @param OrderDTO $dto The OrderDTO object mapped from the request payload.
     * @param OrderFactory $orderFactory The factory service for creating Order entities from DTOs.
     * @return JsonResponse A JSON response indicating the success or failure of the creation.
     */
    #[Route('/', name: 'app_admin_api_orders_create', methods: ['POST'])]
    public function createOrder(
        #[MapRequestPayload] OrderDTO $dto,
        OrderFactory $orderFactory,
    ): JsonResponse {
        [$order, $errors] = $orderFactory->createFromDTO($dto);

        if ($errors->count() > 0) {
            $response = OrderApiResponseDTO::error('Validation failed', [(string) $errors]);
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $this->orderRepository->save($order);
        
        $response = OrderApiResponseDTO::success('Order created successfully');
        return $this->json($response->toArray(), Response::HTTP_CREATED);
    }

    /**
     * Updates an existing order by its ID.
     *
     * @param Order $order The Order entity resolved by the route parameter.
     * @param UpdateOrderDTO $dto The DTO containing update data.
     * @param OrderFactory $orderFactory The factory service for updating orders.
     * @return JsonResponse A JSON response indicating the success or failure of the update.
     */
    #[Route('/{id}', name: 'app_admin_api_orders_update', methods: ['PUT'])]
    public function updateOrder(
        Order $order, 
        #[MapRequestPayload] UpdateOrderDTO $dto,
        OrderFactory $orderFactory
    ): JsonResponse {
        [$updatedOrder, $errors] = $orderFactory->updateFromDTO($order, $dto);

        if ($errors->count() > 0) {
            $response = OrderApiResponseDTO::error('Validation failed', [(string) $errors]);
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $this->orderRepository->save($updatedOrder);
        
        $response = OrderApiResponseDTO::success('Order updated successfully');
        return $this->json($response->toArray());
    }
}