<?php

declare(strict_types=1);

namespace App\Enum;

enum Type: string {
    case ALL_INCLUSIVE = 'all_inclusive';
    case MINIMUM = 'minimum';
    case RENT_ONLY = 'rent_only';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match($this) {
            self::ALL_INCLUSIVE => 'Все включено',
            self::MINIMUM => 'Минимум услуг',
            self::RENT_ONLY => 'Только аренда байдарок',
            self::OTHER => 'Другое',
        };
    }
}