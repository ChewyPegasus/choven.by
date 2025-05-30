<?php

namespace App\Enum;

enum Type: int {
    case ALL_INCLUSIVE = 1;
    case MINIMUM = 2;
    case RENT_ONLY = 3;
    case OTHER = 4;

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