<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service for managing and providing map route data.
 *
 * This service is responsible for loading route definitions from JSON files,
 * translating their content, and providing access to individual routes or
 * all routes in various formats. It caches loaded routes for performance.
 */
class MapService
{
    /**
     * @var array<string, array<string, mixed>>|null A cache for loaded and translated route data.
     */
    private ?array $routesCache = null;

    /**
     * Constructs a new MapService instance.
     *
     * @param TranslatorInterface $translator The Symfony translator service for internationalization.
     * @param string $projectDir The root directory of the Symfony project, injected via autowiring.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {
    }

    /**
     * Loads and translates route data from JSON files.
     *
     * This method reads JSON files from the `assets/data/routes` directory,
     * decodes their content, and translates specific fields using the `TranslatorInterface`.
     * The loaded and translated routes are then cached for subsequent calls.
     *
     * @return array<string, array<string, mixed>> An associative array of route data, keyed by route ID.
     * @throws \RuntimeException If the routes directory is not found or if a JSON file is invalid.
     */
    private function loadRoutesFromFiles(): array
    {
        // Return cached routes if already loaded
        if ($this->routesCache !== null) {
            return $this->routesCache;
        }

        $routesDir = $this->projectDir . '/assets/data/routes';
        $routes = [];

        // Check if the routes directory exists
        if (!is_dir($routesDir)) {
            throw new \RuntimeException("Routes directory not found: $routesDir");
        }

        // Get all JSON files in the routes directory
        $files = glob($routesDir . '/*.json');
        
        foreach ($files as $file) {
            $routeId = basename($file, '.json'); // Use filename as route ID
            $fileContent = file_get_contents($file);

            if ($fileContent === false) {
                throw new \RuntimeException("Failed to read file: $file");
            }

            $routeData = json_decode($fileContent, true);
            
            // Check for JSON decoding errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(sprintf('Invalid JSON in file "%s": %s', $file, json_last_error_msg()));
            }

            // Translate the route data and store it
            $routes[$routeId] = $this->translateRouteData($routeData);
        }

        $this->routesCache = $routes; // Cache the loaded routes
        
        return $routes;
    }

    /**
     * Translates specific fields within a route's data array.
     *
     * This helper method iterates through predefined fields ('name', 'difficulty',
     * 'duration', 'distance', 'description') and the 'points' array, applying
     * translation to their respective values.
     *
     * @param array<string, mixed> $routeData The raw route data array from a JSON file.
     * @return array<string, mixed> The route data array with translated fields.
     */
    private function translateRouteData(array $routeData): array
    {
        return [
            'name' => $this->translator->trans($routeData['name']),
            'color' => $routeData['color'], // Color is not translated
            'difficulty' => $this->translator->trans($routeData['difficulty']),
            'duration' => $this->translator->trans($routeData['duration']),
            'distance' => $this->translator->trans($routeData['distance']),
            'description' => $this->translator->trans($routeData['description']),
            'points' => array_map(function (array $point) {
                // Translate name and description for each point
                return [
                    'coordinates' => $point['coordinates'],
                    'type' => $point['type'],
                    'name' => $this->translator->trans($point['name']),
                    'description' => $this->translator->trans($point['description'])
                ];
            }, $routeData['points'])
        ];
    }

    /**
     * Retrieves all loaded and translated routes.
     *
     * This method ensures routes are loaded from files (and cached) before returning them.
     *
     * @return array<string, array<string, mixed>> An associative array of all available routes.
     */
    public function getAllRoutes(): array
    {
        return $this->loadRoutesFromFiles();
    }

    /**
     * Retrieves a single route by its ID.
     *
     * @param string $routeId The unique identifier of the route.
     * @return array<string, mixed>|null The route data if found, otherwise null.
     */
    public function getRoute(string $routeId): ?array
    {
        $routes = $this->loadRoutesFromFiles();
        
        return $routes[$routeId] ?? null;
    }

    /**
     * Returns all routes as a JSON-encoded string.
     *
     * The JSON is encoded with `JSON_UNESCAPED_UNICODE` for proper handling of
     * multi-byte characters (e.g., Cyrillic).
     *
     * @return string A JSON string representation of all routes.
     */
    public function getRoutesForJson(): string
    {
        return json_encode($this->getAllRoutes(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Retrieves an array of translated labels specifically for map-related UI elements.
     *
     * @return array<string, string> An associative array of translated labels.
     */
    public function getTranslatedLabels(): array
    {
        return [
            'title' => $this->translator->trans('main.maps.title'),
            'description' => $this->translator->trans('main.maps.description'),
            'routes_title' => $this->translator->trans('main.maps.routes.title'),
            'show_route' => $this->translator->trans('main.maps.show_route'),
            'show_all' => $this->translator->trans('main.maps.show_all'),
            'reset' => $this->translator->trans('main.maps.reset'),
        ];
    }
}