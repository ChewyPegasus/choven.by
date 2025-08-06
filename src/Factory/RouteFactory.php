<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\Route\CreateRouteDTO;
use App\DTO\Route\UpdateRouteDTO;
use App\Exception\ValidationException;
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
     * Processes CreateRouteDTO and returns sanitized data with validation.
     *
     * @param CreateRouteDTO $dto
     * @return array [sanitizedRouteId, routeData]
     * @throws ValidationException If validation fails.
     */
    public function processCreateDTO(CreateRouteDTO $dto): array
    {
        $errors = $this->validator->validate($dto);
        
        if ($errors->count() > 0) {
            throw new ValidationException($errors);
        }

        $sanitizedRouteId = $this->routeService->sanitizeRouteId($dto->id);
        
        if (empty($sanitizedRouteId)) {
            throw new ValidationException(
                new \Symfony\Component\Validator\ConstraintViolationList([
                    new \Symfony\Component\Validator\ConstraintViolation(
                        'Invalid route ID format',
                        null,
                        [],
                        $dto,
                        'id',
                        $dto->id
                    )
                ])
            );
        }
        
        return [$sanitizedRouteId, $dto->data];
    }

    /**
     * Processes UpdateRouteDTO and returns validated data.
     *
     * @param UpdateRouteDTO $dto
     * @return array [routeData]
     * @throws ValidationException If validation fails.
     */
    public function processUpdateDTO(UpdateRouteDTO $dto): array
    {
        $errors = $this->validator->validate($dto);
        
        if ($errors->count() > 0) {
            throw new ValidationException($errors);
        }
        
        return [$dto->data];
    }
}