<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class MapService
{
    private ?array $routesCache = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {
    }

    private function loadRoutesFromFiles(): array
    {
        if ($this->routesCache !== null) {
            return $this->routesCache;
        }

        $routesDir = $this->projectDir . '/assets/data/routes';
        $routes = [];

        if (!is_dir($routesDir)) {
            throw new \RuntimeException("Routes directory not found: $routesDir");
        }

        $files = glob($routesDir . '/*.json');
        
        foreach ($files as $file) {
            $routeId = basename($file, '.json');
            $routeData = json_decode(file_get_contents($file), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON in file: $file");
            }

            $routes[$routeId] = $this->translateRouteData($routeData);
        }

        $this->routesCache = $routes;
        return $routes;
    }

    private function translateRouteData(array $routeData): array
    {
        return [
            'name' => $this->translator->trans($routeData['name']),
            'color' => $routeData['color'],
            'difficulty' => $this->translator->trans($routeData['difficulty']),
            'duration' => $this->translator->trans($routeData['duration']),
            'distance' => $this->translator->trans($routeData['distance']),
            'description' => $this->translator->trans($routeData['description']),
            'points' => array_map(function ($point) {
                return [
                    'coordinates' => $point['coordinates'],
                    'type' => $point['type'],
                    'name' => $this->translator->trans($point['name']),
                    'description' => $this->translator->trans($point['description'])
                ];
            }, $routeData['points'])
        ];
    }

    public function getAllRoutes(): array
    {
        return $this->loadRoutesFromFiles();
    }

    public function getRoute(string $routeId): ?array
    {
        $routes = $this->loadRoutesFromFiles();
        return $routes[$routeId] ?? null;
    }

    public function getRoutesForJson(): string
    {
        return json_encode($this->getAllRoutes(), JSON_UNESCAPED_UNICODE);
    }

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
