<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\OrderDTO;
use App\Entity\Order;
use App\Enum\Package;
use App\Enum\River;
use App\Factory\OrderFactory;
use App\Repository\Interfaces\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/orders')]
#[IsGranted('ROLE_ADMIN')]
class OrdersApiController extends AbstractController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/{id}', name: 'app_admin_api_orders_get', methods: ['GET'])]
    public function getOrderById(Order $order): JsonResponse
    {
        return $this->json([
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
        ]);
    }

    #[Route('/{id}', name: 'app_admin_api_orders_delete', methods: ['DELETE'])]
    public function deleteOrder(Order $order): JsonResponse
    {
        $this->orderRepository->remove($order);

        return $this->json(['success' => true, 'message' => 'Order deleted successfully']);
    }

    #[Route('/', name: 'app_admin_api_orders_create', methods: ['POST'])]
    public function createOrder(
        #[MapRequestPayload] OrderDTO $dto,
        OrderFactory $orderFactory,
    ): JsonResponse
    {
        [$order, $errors] = $orderFactory->createFromDTO($dto);

        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->orderRepository->save($order);

        return $this->json(['success' => true, 'message' => 'Order created successfully'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_admin_api_orders_update', methods: ['PUT'])]
    public function updateOrder(Order $order, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $order->setEmail($data['email'] ?? $order->getEmail());
        $order->setStartDate(new \DateTime($data['startDate'] ?? $order->getStartDate()->format('Y-m-d')));
        $order->setRiver(River::tryFrom($data['river'] ?? $order->getRiver()->value));
        $order->setPackage(Package::tryFrom($data['package'] ?? $order->getPackage()->value));
        $order->setAmountOfPeople((int)($data['amountOfPeople'] ?? $order->getAmountOfPeople()));
        $order->setDurationDays((int)($data['durationDays'] ?? $order->getDurationDays()));
        $order->setDescription($data['description'] ?? $order->getDescription());

        $errors = $validator->validate($order);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->orderRepository->flush();

        return $this->json(['success' => true, 'message' => 'Order updated successfully']);
    }
}
