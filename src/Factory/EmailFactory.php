<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\DTO;
use App\DTO\OrderConfirmationDTO;
use App\DTO\VerificationDTO;
use App\Enum\EmailType;

class EmailFactory
{
    public function create(EmailType $type, array $data): DTO
    {
        return match($type) {
            EmailType::ORDER_CONFIRMATION => new OrderConfirmationDTO($data['order']),
            EmailType::VERIFICATION => new VerificationDTO(
                $data['user'],
                $data['confirmUrl'],
            ),
        };
    }
}
