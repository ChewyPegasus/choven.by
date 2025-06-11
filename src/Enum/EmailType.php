<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailType {
    case ORDER_CONFIRMATION;
    case VERIFICATION;
}
