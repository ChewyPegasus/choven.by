<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\Route\CreateRouteDTO;
use App\DTO\Route\UpdateRouteDTO;
use App\Service\RouteService;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Factory for processing route DTOs.
 */
class RouteFactory
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly RouteService $routeService,
    ) {
    }

    /**
     * Processes CreateRouteDTO and returns sanitized data with validation errors.
     */
    public function processCreateDTO(CreateRouteDTO $dto): array
    {
        $errors = $this->validator->validate($dto);
        
        if ($errors->count() > 0) {
            return [null, null, $errors];
        }

        $sanitizedRouteId = $this->routeService->sanitizeRouteId($dto->id);
        
        return [$sanitizedRouteId, $dto->data, $errors];
    }

    /**
     * Processes UpdateRouteDTO and returns validated data with validation errors.
     */
    public function processUpdateDTO(UpdateRouteDTO $dto): array
    {
        $errors = $this->validator->validate($dto);
        
        return [$dto->data, $errors];
    }
}