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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
class RoutesApiController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RouteService $routeService,
    )
    {
    }

    private function getRoutesDirectory(): string
    {
        return $this->getParameter('kernel.project_dir') . '/assets/data/routes/';
    }

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
