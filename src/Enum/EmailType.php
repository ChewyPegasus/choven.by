<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailType: string {
    case ORDER_CONFIRMATION = 'order_confirmation';
    case VERIFICATION = 'verification';
}
