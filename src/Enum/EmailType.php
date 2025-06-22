<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailType {
    case ORDER_CONFIRMATION;
    case VERIFICATION;

    public static function from(string $type): self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $type) {
                return $case;
            }
        }

        throw new \InvalidArgumentException("Invalid email type: $type");
    }
}
