<?php

declare(strict_types=1);

namespace App\Controller\Api;

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
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($routeData);
    }

    /**
     * Creates a new route from the request content.
     */
    #[Route('/', name: 'app_admin_api_routes_create', methods: ['POST'])]
    public function createRoute(Request $request): JsonResponse
    {
        $requestData = $this->decodeJsonRequest($request);
        if ($requestData === null) {
            return $this->json(['error' => 'Invalid JSON request'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->validateCreateRequest($requestData)) {
            return $this->json(['error' => 'Invalid request data. "id" and "data" are required.'], Response::HTTP_BAD_REQUEST);
        }

        $routeId = $this->routeService->sanitizeRouteId($requestData['id']);
        if (empty($routeId)) {
            return $this->json(['error' => 'Invalid route ID format'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->routeRepository->exists($routeId)) {
            return $this->json(['error' => 'Route with this ID already exists'], Response::HTTP_CONFLICT);
        }

        if (!$this->routeRepository->save($routeId, $requestData['data'])) {
            return $this->json(['error' => 'Failed to save route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route created successfully'], Response::HTTP_CREATED);
    }

    /**
     * Updates an existing route by its ID.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_update', methods: ['PUT'])]
    public function updateRoute(string $routeId, Request $request): JsonResponse
    {
        if (!$this->routeRepository->exists($routeId)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $routeData = $this->decodeJsonRequest($request);
        if ($routeData === null) {
            return $this->json(['error' => 'Invalid JSON data provided'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->routeRepository->save($routeId, $routeData)) {
            return $this->json(['error' => 'Failed to update route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route updated successfully']);
    }

    /**
     * Deletes a route by its ID.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_delete', methods: ['DELETE'])]
    public function deleteRoute(string $routeId): JsonResponse
    {
        if (!$this->routeRepository->exists($routeId)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->routeRepository->delete($routeId)) {
            return $this->json(['error' => 'Failed to delete route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route deleted successfully']);
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