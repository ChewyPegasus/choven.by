<?php

declare(strict_types=1);

namespace App\DTO\ApiResponse;

/**
 * DTO for Route API responses.
 */
class RouteApiResponseDTO extends ApiResponseDTO
{
    public function __construct(
        bool $success,
        ?string $message = null,
        ?array $errors = null,
        public readonly ?array $route = null,
        public readonly ?array $routes = null,
    ) {
        parent::__construct($success, $message, $errors);
    }

    public static function successWithRoute(string $message, array $route): self
    {
        return new self(true, $message, null, $route);
    }

    public static function successWithRoutes(string $message, array $routes): self
    {
        return new self(true, $message, null, null, $routes);
    }

    protected function getAdditionalData(): array
    {
        $data = [];
        
        if ($this->route !== null) {
            $data = array_merge($data, $this->route);
        }
        
        if ($this->routes !== null) {
            $data['routes'] = $this->routes;
        }
        
        return $data;
    }
}