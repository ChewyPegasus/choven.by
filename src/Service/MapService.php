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
                        'coordinates' => [53.9012, 27.5623],
                        'type' => 'start',
                        'name' => $this->translator->trans('routes.isloch.points.start.name'),
                        'description' => $this->translator->trans('routes.isloch.points.start.description')
                    ],
                    [
                        'coordinates' => [53.8800, 27.5200],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.isloch.points.borovlyany.name'),
                        'description' => $this->translator->trans('routes.isloch.points.borovlyany.description')
                    ],
                    [
                        'coordinates' => [53.8600, 27.4800],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.isloch.points.zaslavl.name'),
                        'description' => $this->translator->trans('routes.isloch.points.zaslavl.description')
                    ],
                    [
                        'coordinates' => [53.8400, 27.4400],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.isloch.points.radoshkovichi.name'),
                        'description' => $this->translator->trans('routes.isloch.points.radoshkovichi.description')
                    ],
                    [
                        'coordinates' => [53.8200, 27.4000],
                        'type' => 'end',
                        'name' => $this->translator->trans('routes.isloch.points.end.name'),
                        'description' => $this->translator->trans('routes.isloch.points.end.description')
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
                        'coordinates' => [53.9100, 27.5700],
                        'type' => 'start',
                        'name' => $this->translator->trans('routes.svisloch.points.start.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.start.description')
                    ],
                    [
                        'coordinates' => [53.8950, 27.5500],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.svisloch.points.park.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.park.description')
                    ],
                    [
                        'coordinates' => [53.8800, 27.5300],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.svisloch.points.bridge.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.bridge.description')
                    ],
                    [
                        'coordinates' => [53.8650, 27.5100],
                        'type' => 'end',
                        'name' => $this->translator->trans('routes.svisloch.points.end.name'),
                        'description' => $this->translator->trans('routes.svisloch.points.end.description')
                    ]
                ]
            ],
            'berezina' => [
                'name' => $this->translator->trans('river.berezina'),
                'color' => '#dc3545',
                'difficulty' => $this->translator->trans('routes.difficulty.high'),
                'duration' => $this->translator->trans('routes.berezina.duration'),
                'distance' => $this->translator->trans('routes.berezina.distance'),
                'description' => $this->translator->trans('routes.berezina.description'),
                'points' => [
                    [
                        'coordinates' => [53.7800, 28.3400],
                        'type' => 'start',
                        'name' => $this->translator->trans('routes.berezina.points.start.name'),
                        'description' => $this->translator->trans('routes.berezina.points.start.description')
                    ],
                    [
                        'coordinates' => [53.7600, 28.3800],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.berezina.points.berezino.name'),
                        'description' => $this->translator->trans('routes.berezina.points.berezino.description')
                    ],
                    [
                        'coordinates' => [53.7400, 28.4200],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.berezina.points.camping.name'),
                        'description' => $this->translator->trans('routes.berezina.points.camping.description')
                    ],
                    [
                        'coordinates' => [53.7200, 28.4600],
                        'type' => 'point',
                        'name' => $this->translator->trans('routes.berezina.points.rapids.name'),
                        'description' => $this->translator->trans('routes.berezina.points.rapids.description')
                    ],
                    [
                        'coordinates' => [53.7000, 28.5000],
                        'type' => 'end',
                        'name' => $this->translator->trans('routes.berezina.points.end.name'),
                        'description' => $this->translator->trans('routes.berezina.points.end.description')
                    ]
                ]
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