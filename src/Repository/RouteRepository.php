<?php

declare(strict_types=1);

namespace App\Repository;

use App\Repository\Interfaces\RouteRepositoryInterface;
use App\Service\RouteService;
use Psr\Log\LoggerInterface;

/**
 * File-based repository for route data management.
 */
class RouteRepository implements RouteRepositoryInterface
{
    public function __construct(
        private readonly RouteService $routeService,
        private readonly string $routesDirectory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Finds a route by its ID.
     */
    public function findById(string $routeId): ?array
    {
        $filePath = $this->getFilePath($routeId);
        
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                $this->logger->error('Failed to read route file', ['routeId' => $routeId, 'path' => $filePath]);
                return null;
            }

            $routeData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON in route file', [
                    'routeId' => $routeId, 
                    'path' => $filePath,
                    'jsonError' => json_last_error_msg()
                ]);
                return null;
            }

            return $routeData;
        } catch (\Exception $e) {
            $this->logger->error('Exception while reading route', [
                'routeId' => $routeId,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Saves a route.
     */
    public function save(string $routeId, array $routeData): bool
    {
        try {
            return $this->routeService->saveRoute($routeId, $routeData);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save route', [
                'routeId' => $routeId,
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Checks if a route exists.
     */
    public function exists(string $routeId): bool
    {
        return $this->routeService->routeExists($routeId);
    }

    /**
     * Deletes a route.
     */
    public function delete(string $routeId): bool
    {
        try {
            return $this->routeService->deleteRoute($routeId);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete route', [
                'routeId' => $routeId,
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Gets all available routes.
     */
    public function findAll(): array
    {
        try {
            $routes = [];
            $files = glob($this->routesDirectory . '/*.json');
            
            if ($files === false) {
                return [];
            }

            foreach ($files as $file) {
                $routeId = basename($file, '.json');
                $routeData = $this->findById($routeId);
                
                if ($routeData !== null) {
                    $routes[$routeId] = $routeData;
                }
            }

            return $routes;
        } catch (\Exception $e) {
            $this->logger->error('Failed to load all routes', [
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Gets the file path for a route.
     */
    private function getFilePath(string $routeId): string
    {
        return $this->routesDirectory . '/' . $routeId . '.json';
    }
}