<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Order;
use App\Enum\EmailTemplate;

readonly class OrderConfirmationDTO extends AbstractEmailDTO
{
    public function __construct(private Order $order)
    {
    }

    public function getEntity(): object
    {
        return $this->order;
    }

    public function getEmailTemplate(): EmailTemplate
    {
        return EmailTemplate::ORDER_CONFIRMATION;
    }
    
    public function getAdditionalContext(): array
    {
        return [
            'order' => $this->order,
        ];
    }
}
