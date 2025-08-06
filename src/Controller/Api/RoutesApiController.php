<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ApiResponse\RouteApiResponseDTO;
use App\DTO\Route\CreateRouteDTO;
use App\DTO\Route\UpdateRouteDTO;
use App\Exception\ValidationException;
use App\Exception\ValidationHttpException;
use App\Factory\RouteFactory;
use App\Repository\Interfaces\RouteRepositoryInterface;
use App\Service\RouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * API controller for managing route data, accessible by administrators.
 */
#[Route('/api/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
class RoutesApiController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RouteService $routeService,
        private readonly RouteRepositoryInterface $routeRepository,
        private readonly RouteFactory $routeFactory,
    ) {
    }

    /**
     * Retrieves a single route by its ID.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_get', methods: ['GET'])]
    public function getRoute(string $routeId): JsonResponse
    {
        $routeData = $this->routeRepository->findById($routeId);
        
        if ($routeData === null) {
            $response = RouteApiResponseDTO::error('Route not found');
            return $this->json($response->toArray(), Response::HTTP_NOT_FOUND);
        }

        $response = RouteApiResponseDTO::successWithRoute('Route retrieved successfully', $routeData);
        return $this->json($response->toArray());
    }

    /**
     * Creates a new route from the request content.
     */
    #[Route('/', name: 'app_admin_api_routes_create', methods: ['POST'])]
    public function createRoute(
        #[MapRequestPayload] CreateRouteDTO $dto,
    ): JsonResponse {
        try {
            [$sanitizedRouteId, $routeData] = $this->routeFactory->processCreateDTO($dto);

            if ($this->routeRepository->exists($sanitizedRouteId)) {
                $response = RouteApiResponseDTO::error('Route with this ID already exists');
                return $this->json($response->toArray(), Response::HTTP_CONFLICT);
            }

            if (!$this->routeRepository->save($sanitizedRouteId, $routeData)) {
                $response = RouteApiResponseDTO::error('Failed to save route file');
                return $this->json($response->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $response = RouteApiResponseDTO::success('Route created successfully');
            return $this->json($response->toArray(), Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            throw new ValidationHttpException($e->getViolations(), 'Validation failed');
        }
    }

    /**
     * Updates an existing route by its ID.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_update', methods: ['PUT'])]
    public function updateRoute(
        string $routeId,
        #[MapRequestPayload] UpdateRouteDTO $dto,
    ): JsonResponse {
        if (!$this->routeRepository->exists($routeId)) {
            $response = RouteApiResponseDTO::error('Route not found');
            return $this->json($response->toArray(), Response::HTTP_NOT_FOUND);
        }

        try {
            [$routeData] = $this->routeFactory->processUpdateDTO($dto);

            if (!$this->routeRepository->save($routeId, $routeData)) {
                $response = RouteApiResponseDTO::error('Failed to update route file');
                return $this->json($response->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $response = RouteApiResponseDTO::success('Route updated successfully');
            return $this->json($response->toArray());
        } catch (ValidationException $e) {
            throw new ValidationHttpException($e->getViolations(), 'Validation failed');
        }
    }

    /**
     * Deletes a route by its ID.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_delete', methods: ['DELETE'])]
    public function deleteRoute(string $routeId): JsonResponse
    {
        if (!$this->routeRepository->exists($routeId)) {
            $response = RouteApiResponseDTO::error('Route not found');
            return $this->json($response->toArray(), Response::HTTP_NOT_FOUND);
        }

        if (!$this->routeRepository->delete($routeId)) {
            $response = RouteApiResponseDTO::error('Failed to delete route file');
            return $this->json($response->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = RouteApiResponseDTO::success('Route deleted successfully');
        return $this->json($response->toArray());
    }

    /**
     * Retrieves all available routes.
     */
    #[Route('/', name: 'app_admin_api_routes_list', methods: ['GET'])]
    public function getAllRoutes(): JsonResponse
    {
        $routes = $this->routeRepository->findAll();
        
        if (empty($routes)) {
            $response = RouteApiResponseDTO::successWithRoutes('No routes found', []);
            return $this->json($response->toArray());
        }

        $response = RouteApiResponseDTO::successWithRoutes('Routes retrieved successfully', $routes);
        return $this->json($response->toArray());
    }
}