<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ApiResponse\RouteApiResponseDTO;
use App\Repository\Interfaces\RouteRepositoryInterface;
use App\Service\RouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function createRoute(Request $request): JsonResponse
    {
        $requestData = $this->decodeJsonRequest($request);
        if ($requestData === null) {
            $response = RouteApiResponseDTO::error('Invalid JSON request');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        if (!$this->validateCreateRequest($requestData)) {
            $response = RouteApiResponseDTO::error('Invalid request data. "id" and "data" are required.');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $routeId = $this->routeService->sanitizeRouteId($requestData['id']);
        if (empty($routeId)) {
            $response = RouteApiResponseDTO::error('Invalid route ID format');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        if ($this->routeRepository->exists($routeId)) {
            $response = RouteApiResponseDTO::error('Route with this ID already exists');
            return $this->json($response->toArray(), Response::HTTP_CONFLICT);
        }

        if (!$this->routeRepository->save($routeId, $requestData['data'])) {
            $response = RouteApiResponseDTO::error('Failed to save route file');
            return $this->json($response->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = RouteApiResponseDTO::success('Route created successfully');
        return $this->json($response->toArray(), Response::HTTP_CREATED);
    }

    /**
     * Updates an existing route by its ID.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_update', methods: ['PUT'])]
    public function updateRoute(string $routeId, Request $request): JsonResponse
    {
        if (!$this->routeRepository->exists($routeId)) {
            $response = RouteApiResponseDTO::error('Route not found');
            return $this->json($response->toArray(), Response::HTTP_NOT_FOUND);
        }

        $routeData = $this->decodeJsonRequest($request);
        if ($routeData === null) {
            $response = RouteApiResponseDTO::error('Invalid JSON data provided');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        if (!$this->routeRepository->save($routeId, $routeData)) {
            $response = RouteApiResponseDTO::error('Failed to update route file');
            return $this->json($response->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = RouteApiResponseDTO::success('Route updated successfully');
        return $this->json($response->toArray());
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

    /**
     * Decodes JSON request content.
     */
    private function decodeJsonRequest(Request $request): ?array
    {
        $content = $request->getContent();
        $decoded = json_decode($content, true);
        
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Validates create request data.
     */
    private function validateCreateRequest(array $data): bool
    {
        return isset($data['id']) && isset($data['data']);
    }
}