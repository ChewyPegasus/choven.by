<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\DTO;
use App\DTO\OrderConfirmationDTO;
use App\DTO\VerificationDTO;
use App\Enum\DTOType;

class DTOFactory
{
    public function create(DTOType $type, array $data): DTO
    {
        return match($type) {
            DTOType::ORDER_CONFIRMATION => new OrderConfirmationDTO($data['order']),
            DTOType::VERIFICATION => new VerificationDTO(
                $data['user'],
                $data['confirmUrl'],
            ),
        };
    }
}
