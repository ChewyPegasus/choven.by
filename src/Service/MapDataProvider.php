<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RaftingRoute;
use App\Model\Localized;
use App\Repository\RaftingRouteRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Prepares route data for the Leaflet map in the visitor's language.
 */
class MapDataProvider
{
    public function __construct(
        private readonly RaftingRouteRepository $routes,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRoutes(string $locale): array
    {
        return array_map(fn (RaftingRoute $route): array => $this->export($route, $locale), $this->routes->findActive());
    }

    /**
     * @return array<string, mixed>
     */
    public function export(RaftingRoute $route, string $locale): array
    {
        return [
            'id' => $route->getSlug(),
            'name' => $route->getLocalizedName($locale),
            'description' => $route->getLocalizedDescription($locale),
            'duration' => $route->getLocalizedDuration($locale),
            'distanceKm' => $route->getDistanceKm(),
            'difficulty' => $route->getDifficulty()->value,
            'difficultyLabel' => $route->getDifficulty()->trans($this->translator, $locale),
            'color' => $route->getColor(),
            'river' => $route->getRiver()?->value,
            'points' => array_map(static fn (array $point): array => [
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'type' => $point['type'],
                'name' => Localized::pick($point['name'], $locale),
                'description' => Localized::pick($point['description'], $locale),
            ], $route->getPoints()),
        ];
    }
}
