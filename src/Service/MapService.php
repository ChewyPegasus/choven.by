<?php

declare(strict_types=1);

namespace App\Service;

class MapService
{
    private array $routes = [
        'isloch' => [
            'name' => 'Река Ислочь',
            'description' => 'Живописный маршрут по реке Ислочь',
            'coordinates' => [
                [53.9006, 27.5590], // Начальная точка
                [53.8956, 27.5645],
                [53.8906, 27.5700],
                [53.8856, 27.5755],
                [53.8806, 27.5810], // Конечная точка
            ],
            'center' => [53.8906, 27.5700],
            'zoom' => 13,
            'duration' => '2 дня',
            'difficulty' => 'Средняя',
            'distance' => '25 км'
        ],
        'svisloch' => [
            'name' => 'Река Свислочь',
            'description' => 'Короткий маршрут для новичков',
            'coordinates' => [
                [53.9000, 27.5500],
                [53.8950, 27.5550],
                [53.8900, 27.5600],
                [53.8850, 27.5650],
                [53.8800, 27.5700],
            ],
            'center' => [53.8900, 27.5600],
            'zoom' => 13,
            'duration' => '1 день',
            'difficulty' => 'Легкая',
            'distance' => '15 км'
        ],
        'all_inclusive' => [
            'name' => 'All Inclusive маршрут',
            'description' => 'Полный пакет с ночевкой',
            'coordinates' => [
                [53.9200, 27.5300],
                [53.9150, 27.5350],
                [53.9100, 27.5400],
                [53.9050, 27.5450],
                [53.9000, 27.5500],
                [53.8950, 27.5550],
                [53.8900, 27.5600],
            ],
            'center' => [53.9050, 27.5450],
            'zoom' => 12,
            'duration' => '2-3 дня',
            'difficulty' => 'Высокая',
            'distance' => '40 км'
        ]
    ];

    public function getAllRoutes(): array
    {
        return $this->routes;
    }

    public function getRoute(string $routeId): ?array
    {
        return $this->routes[$routeId] ?? null;
    }

    public function getRoutesForJson(): string
    {
        return json_encode($this->routes, JSON_UNESCAPED_UNICODE);
    }
}