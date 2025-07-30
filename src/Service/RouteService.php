<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Service for managing route data files.
 *
 * This service provides methods for constructing file paths for route data,
 * sanitizing route IDs, checking for the existence of route files,
 * saving new or updated route data, and deleting route files.
 */
class RouteService 
{
    /**
     * @var string The base directory where route JSON files are stored.
     */
    private string $routesDir;

    /**
     * Constructs a new RouteService instance.
     *
     * @param string $projectDir The root directory of the Symfony project.
     */
    public function __construct(string $projectDir)
    {
        $this->routesDir = $projectDir . '/assets/data/routes/';
    }

    /**
     * Generates the full file path for a given route ID.
     *
     * The route ID is sanitized before constructing the path to prevent directory traversal vulnerabilities.
     *
     * @param string $routeId The ID of the route.
     * @return string The absolute file path to the route's JSON file.
     */
    public function getRouteFilePath(string $routeId): string
    {
        return $this->routesDir . $this->sanitizeRouteId($routeId) . '.json';
    }

    /**
     * Sanitizes a route ID by removing any characters that are not alphanumeric, hyphens, or underscores.
     *
     * This helps prevent security issues such as directory traversal.
     *
     * @param string $id The route ID to sanitize.
     * @return string The sanitized route ID.
     */
    public function sanitizeRouteId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    }

    /**
     * Checks if a route data file exists for a given route ID.
     *
     * @param string $routeId The ID of the route to check.
     * @return bool True if the route file exists, false otherwise.
     */
    public function routeExists(string $routeId): bool
    {
        return file_exists($this->getRouteFilePath($routeId));
    }

    /**
     * Saves route data to a JSON file.
     *
     * The data is JSON-encoded with pretty printing and unescaped Unicode characters.
     *
     * @param string $routeId The ID of the route to save.
     * @param array<string, mixed> $data The associative array of data to save.
     * @return bool True on successful write, false on failure.
     */
    public function saveRoute(string $routeId, array $data): bool
    {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonContent === false) {
            // Handle JSON encoding error, perhaps log it
            return false;
        }
        return file_put_contents($this->getRouteFilePath($routeId), $jsonContent) !== false;
    }

    /**
     * Deletes a route data file for a given route ID.
     *
     * @param string $routeId The ID of the route to delete.
     * @return bool True on successful deletion or if the file did not exist, false on failure to delete an existing file.
     */
    public function deleteRoute(string $routeId): bool
    {
        $filePath = $this->getRouteFilePath($routeId);
        // Check if the file exists before attempting to delete it
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false; // File did not exist, so nothing to delete, return false or true based on desired behavior
                      // Returning false explicitly states that no deletion occurred because the file wasn't there.
    }
}