<?php

declare(strict_types=1);

namespace App\Enum;

enum Type: string {
    case ALL_INCLUSIVE = 'all_inclusive';
    case MINIMUM = 'minimum';
    case RENT_ONLY = 'rent_only';
    case OTHER = 'other';

    /**
     * Возвращает ключ для перевода
     */
    public function getLabel(): string
    {
        return match($this) {
            self::ALL_INCLUSIVE => 'type.all_inclusive',
            self::MINIMUM => 'type.minimum',
            self::RENT_ONLY => 'type.rent_only',
            self::OTHER => 'type.other',
        };
    }
}