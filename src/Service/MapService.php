<?php
// src/Service/MapService.php - интегрируем переводы

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
                'color' => '#ff6b00',
                'difficulty' => $this->translator->trans('routes.difficulty.medium'),
                'duration' => $this->translator->trans('routes.isloch.duration'),
                'distance' => $this->translator->trans('routes.isloch.distance'),
                'description' => $this->translator->trans('routes.isloch.description'),
                'points' => [
                    [
                        'coordinates' => [53.957456, 26.737684],
                        'type' => 'start',
                        'name' => $this->translator->trans('routes.isloch.points.borov.name'),
                        'description' => $this->translator->trans('routes.isloch.points.borov.description')
                    ],
                    [
                        'coordinates' => [54.009733, 26.642532],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.isloch.points.golubi.name'),
                        'description' => $this->translator->trans('routes.isloch.points.golubi.description')
                    ],
                    [
                        'coordinates' => [54.013528, 26.607166],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.isloch.points.yatskovo.name'),
                        'description' => $this->translator->trans('routes.isloch.points.yatskovo.description')
                    ],
                    [
                        'coordinates' => [54.024217, 26.484207],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.isloch.points.belokorec.name'),
                        'description' => $this->translator->trans('routes.isloch.points.belokorec.description')
                    ],
                    [
                        'coordinates' => [54.013380, 26.414221],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.isloch.points.dorogun.name'),
                        'description' => $this->translator->trans('routes.isloch.points.dorogun.description')
                    ],
                    [
                        'coordinates' => [54.000518, 26.373943],
                        'type' => 'end',
                        'name' => $this->translator->trans('routes.isloch.points.most.name'),
                        'description' => $this->translator->trans('routes.isloch.points.most.description')
                    ]
                ]
            ],
            'svisloch' => [
                'name' => $this->translator->trans('river.svisloch'),
                'color' => '#28a745',
                'difficulty' => $this->translator->trans('routes.difficulty.easy'),
                'duration' => $this->translator->trans('routes.svisloch.duration'),
                'distance' => $this->translator->trans('routes.svisloch.distance'),
                'description' => $this->translator->trans('routes.svisloch.description'),
                'points' => [
                    [
                        'coordinates' => [53.875663, 27.584552],
                        'type' => 'start',
                        'name' => $this->translator->trans('routes.svisloch.points.start.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.start.description')
                    ],
                    [
                        'coordinates' => [53.856594, 27.580547],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.svisloch.points.park.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.park.description')
                    ],
                    [
                        'coordinates' => [53.853520, 27.586773],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.svisloch.points.ruins.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.ruins.description')
                    ],
                    [
                        'coordinates' => [53.848692, 27.589894],
                        'type' => 'end',
                        'name' => $this->translator->trans('routes.svisloch.points.end.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.end.description')
                    ]
                ]
            ],
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