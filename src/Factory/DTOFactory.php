<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\FormOrderDTO;
use App\Enum\Package;
use App\Enum\River;
use Symfony\Component\HttpFoundation\Request;

class DTOFactory {
    public function createFromRequest(Request $request): FormOrderDTO
    {
        $dto = new FormOrderDTO();

        $type = $request->query->get('type');
        $duration = $request->query->get('duration');
        $river = $request->query->get('river');

        if ($type) {
            $dto->package = (Package::from($type));
        }
        if ($river) {
            $dto->river = (River::from($river));
        }
        if ($duration) {
            $dto->duration = $duration;
        }

        return $dto;
    }
}