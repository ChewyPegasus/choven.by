<?php

declare(strict_types=1);

namespace App\Controller\Api;

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
 *
 * This controller provides endpoints to retrieve, create, update, and delete
 * route definitions stored as JSON files. It ensures that only users with
 * 'ROLE_ADMIN' can access these functionalities.
 */
#[Route('/api/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
class RoutesApiController extends AbstractController
{
    /**
     * Constructs a new RoutesApiController instance.
     *
     * @param TranslatorInterface $translator The translator service for internationalization.
     * @param RouteService $routeService The service for managing route file operations.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RouteService $routeService,
    ) {
    }

    /**
     * Returns the absolute path to the directory where route JSON files are stored.
     *
     * @return string The path to the routes directory.
     */
    private function getRoutesDirectory(): string
    {
        return $this->getParameter('kernel.project_dir') . '/assets/data/routes/';
    }

    /**
     * Retrieves a single route by its ID.
     *
     * Fetches the JSON file corresponding to the given route ID and returns its content.
     * Handles cases where the file does not exist or contains invalid JSON.
     *
     * @param string $routeId The ID of the route to retrieve.
     * @return JsonResponse A JSON response containing the route data or an error message.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_get', methods: ['GET'])]
    public function getRoute(string $routeId): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $content = file_get_contents($filePath);
        $routeData = json_decode((string)$content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON in route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($routeData);
    }

    /**
     * Creates a new route from the request content.
     *
     * Expects a JSON payload with 'id' and 'data' keys.
     * Sanitizes the route ID, checks for existing routes, and saves the new route.
     *
     * @param Request $request The HTTP request containing the route data.
     * @return JsonResponse A JSON response indicating the success or failure of the creation.
     */
    #[Route('/', name: 'app_admin_api_routes_create', methods: ['POST'])]
    public function createRoute(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['id']) || !isset($data['data'])) {
            return $this->json(['error' => 'Invalid request data. "id" and "data" are required.'], Response::HTTP_BAD_REQUEST);
        }

        $routeId = $this->routeService->sanitizeRouteId($data['id']);
        $routeData = $data['data'];

        if (empty($routeId)) {
            return $this->json(['error' => 'Invalid route ID format'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->routeService->routeExists($routeId)) {
            return $this->json(['error' => 'Route with this ID already exists'], Response::HTTP_CONFLICT);
        }

        if (!$this->routeService->saveRoute($routeId, $routeData)) {
            return $this->json(['error' => 'Failed to save route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route created successfully'], Response::HTTP_CREATED);
    }

    /**
     * Updates an existing route by its ID.
     *
     * Expects a JSON payload with the updated route data.
     * Overwrites the existing route file with the new content.
     *
     * @param string $routeId The ID of the route to update.
     * @param Request $request The HTTP request containing the updated route data.
     * @return JsonResponse A JSON response indicating the success or failure of the update.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_update', methods: ['PUT'])]
    public function updateRoute(string $routeId, Request $request): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $content = $request->getContent();
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON data provided'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->routeService->saveRoute($routeId, $decoded)) {
            return $this->json(['error' => 'Failed to update route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route updated successfully']);
    }

    /**
     * Deletes a route by its ID.
     *
     * Removes the JSON file corresponding to the given route ID.
     *
     * @param string $routeId The ID of the route to delete.
     * @return JsonResponse A JSON response indicating the success or failure of the deletion.
     */
    #[Route('/{routeId}', name: 'app_admin_api_routes_delete', methods: ['DELETE'])]
    public function deleteRoute(string $routeId): JsonResponse
    {
        if (!$this->routeService->routeExists($routeId)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->routeService->deleteRoute($routeId)) {
            return $this->json(['error' => 'Failed to delete route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route deleted successfully']);
    }
}