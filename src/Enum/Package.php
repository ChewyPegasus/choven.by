<?php

declare(strict_types=1);

namespace App\Enum;

enum Package: string {
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
            self::ALL_INCLUSIVE => 'package.all_inclusive.title',
            self::MINIMUM => 'package.minimum.title',
            self::RENT_ONLY => 'package.rent_only.title',
            self::OTHER => 'package.other.title',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::ALL_INCLUSIVE => 'package.all_inclusive.description',
            self::MINIMUM => 'package.minimum.description',
            self::RENT_ONLY => 'package.rent_only.description',
            self::OTHER => 'package.other.description',
        };
    }
    
    public function getFeatures(): array
    {
        return match($this) {
            self::ALL_INCLUSIVE => [
                'package.all_inclusive.features.transfer',
                'package.all_inclusive.features.equipment',
                'package.all_inclusive.features.instructor',
                'package.all_inclusive.features.food',
                'package.all_inclusive.features.camping',
                'package.all_inclusive.features.insurance'
            ],
            self::MINIMUM => [
                'package.minimum.features.equipment',
                'package.minimum.features.instructor',
                'package.minimum.features.insurance'
            ],
            self::RENT_ONLY => [
                'package.rent_only.features.equipment'
            ],
            self::OTHER => [
                'package.other.features.custom'
            ],
        };
    }
}