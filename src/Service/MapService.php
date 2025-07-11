<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class MapService
{
    private TranslatorInterface $translator;
    
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    private function getRoutes(): array
    {
        return [
            'isloch' => [
                'name' => $this->translator->trans('river.isloch'),
                'description' => $this->translator->trans('routes.isloch.description'),
                'coordinates' => [
                    [53.8976, 27.5455],
                    [53.8845, 27.4983],
                    [53.8712, 27.4521],
                    [53.8579, 27.4059],
                    [53.8446, 27.3597],
                ],
                'center' => [53.8712, 27.4521],
                'zoom' => 11,
                'duration' => $this->translator->trans('routes.isloch.duration'),
                'difficulty' => $this->translator->trans('routes.difficulty.medium'),
                'distance' => $this->translator->trans('routes.isloch.distance')
            ],
            'svisloch' => [
                'name' => $this->translator->trans('river.svisloch'),
                'description' => $this->translator->trans('routes.svisloch.description'),
                'coordinates' => [
                    [53.9067, 27.5615],
                    [53.8934, 27.5234],
                    [53.8801, 27.4853],
                    [53.8668, 27.4472],
                ],
                'center' => [53.8868, 27.5044],
                'zoom' => 12,
                'duration' => $this->translator->trans('routes.svisloch.duration'),
                'difficulty' => $this->translator->trans('routes.difficulty.easy'),
                'distance' => $this->translator->trans('routes.svisloch.distance')
            ],
            'berezina' => [
                'name' => $this->translator->trans('river.berezina'),
                'description' => $this->translator->trans('routes.berezina.description'),
                'coordinates' => [
                    [53.7820, 28.3450],
                    [53.7341, 28.4521],
                    [53.6862, 28.5592],
                    [53.6383, 28.6663],
                    [53.5904, 28.7734],
                ],
                'center' => [53.6862, 28.5592],
                'zoom' => 10,
                'duration' => $this->translator->trans('routes.berezina.duration'),
                'difficulty' => $this->translator->trans('routes.difficulty.high'),
                'distance' => $this->translator->trans('routes.berezina.distance')
            ]
        ];
    }

    public function getAllRoutes(): array
    {
        return $this->getRoutes();
    }

    public function getRoute(string $routeId): ?array
    {
        $routes = $this->getRoutes();
        return $routes[$routeId] ?? null;
    }

    public function getRoutesForJson(): string
    {
        return json_encode($this->getRoutes(), JSON_UNESCAPED_UNICODE);
    }
}
