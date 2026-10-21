<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\Order\FormOrderDTO;
use App\Enum\Package;
use App\Enum\River;
use Symfony\Component\HttpFoundation\Request;

/**
 * Factory for creating Data Transfer Objects (DTOs).
 *
 * This factory is responsible for instantiating and populating DTOs,
 * particularly `FormOrderDTO`, from various data sources like HTTP requests.
 */
class DTOFactory
{
    /**
     * Creates and populates a FormOrderDTO from an HTTP Request object.
     *
     * This method extracts specific query parameters ('type', 'duration', 'river')
     * from the request and attempts to set them on a new `FormOrderDTO` instance.
     * It tries to convert 'type' and 'river' into their corresponding Enum cases.
     *
     * @param Request $request The HTTP request from which to extract data.
     * @return FormOrderDTO A new FormOrderDTO instance populated with request data.
     */
    public function createFromRequest(Request $request): FormOrderDTO
    {
        $dto = new FormOrderDTO();

        // Retrieve query parameters from the request
        $type = $request->query->get('type');
        $duration = $request->query->get('duration');
        $river = $request->query->get('river');

        // Populate DTO properties if parameters are present
        // Use Enum::from() to convert string values to Enum cases
        if ($type) {
            $dto->package = (Package::from($type));
        }
        if ($river) {
            $dto->river = (River::from($river));
        }
        if ($duration) {
            $dto->duration = (int)$duration; // Cast to int as duration is expected as int
        }

        return $dto;
    }
}