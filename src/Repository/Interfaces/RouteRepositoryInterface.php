<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

/**
 * Interface for managing route data persistence.
 */
interface RouteRepositoryInterface
{
    /**
     * Finds a route by its ID.
     *
     * @param string $routeId
     * @return array|null Route data or null if not found
     */
    public function findById(string $routeId): ?array;

    /**
     * Saves a route.
     *
     * @param string $routeId
     * @param array $routeData
     * @return bool True if saved successfully
     */
    public function save(string $routeId, array $routeData): bool;

    /**
     * Checks if a route exists.
     *
     * @param string $routeId
     * @return bool
     */
    public function exists(string $routeId): bool;

    /**
     * Deletes a route.
     *
     * @param string $routeId
     * @return bool True if deleted successfully
     */
    public function delete(string $routeId): bool;

    /**
     * Gets all available routes.
     *
     * @return array
     */
    public function findAll(): array;
}