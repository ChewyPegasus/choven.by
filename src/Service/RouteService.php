<?php

declare(strict_types=1);

namespace App\Service;

class RouteService {
    private string $routesDir;

    public function __construct(string $projectDir)
    {
        $this->routesDir = $projectDir . '/assets/data/routes/';
    }

    public function getRouteFilePath(string $routeId): string
    {
        return $this->routesDir . $this->sanitizeRouteId($routeId) . '.json';
    }

    public function sanitizeRouteId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    }

    public function routeExists(string $routeId): bool
    {
        return file_exists($this->getRouteFilePath($routeId));
    }

    public function saveRoute(string $routeId, array $data): bool
    {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->getRouteFilePath($routeId), $jsonContent) !== false;
    }

    public function deleteRoute(string $routeId): bool
    {
        $filePath = $this->getRouteFilePath($routeId);
        return file_exists($filePath) ? unlink($filePath) : false;
    }
}