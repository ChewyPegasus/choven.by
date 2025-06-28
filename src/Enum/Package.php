<?php

declare(strict_types=1);

namespace App\Enum;

enum Package: string
{
    case ALL_INCLUSIVE = 'all_inclusive';
    case MINIMUM = 'minimum';
    case RENT_ONLY = 'rent_only';
    case CORPORATE = 'corporate';
    case OTHER = 'other';

    /**
     * Returning key for translation
     */
    public function getLabel(): string
    {
        return match($this) {
            self::ALL_INCLUSIVE => 'package.all_inclusive.title',
            self::MINIMUM => 'package.minimum.title',
            self::RENT_ONLY => 'package.rent_only.title',
            self::OTHER => 'package.other.title',
            self::CORPORATE => 'package.corporate.title',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::ALL_INCLUSIVE => 'package.all_inclusive.description',
            self::MINIMUM => 'package.minimum.description',
            self::RENT_ONLY => 'package.rent_only.description',
            self::OTHER => 'package.other.description',
            self::CORPORATE => 'package.corporate.description',
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
            ],
            self::MINIMUM => [
                'package.minimum.features.equipment',
                'package.minimum.features.instructor',
            ],
            self::RENT_ONLY => [
                'package.rent_only.features.equipment'
            ],
            self::OTHER => [
                'package.other.features.custom'
            ],
            self::CORPORATE => [
                'package.corporate.features.team_building',
                'package.corporate.features.equipment',
                'package.corporate.features.instructor',
                'package.corporate.features.food',
                'package.corporate.features.transport',
                'package.corporate.features.group_activities',
                'package.corporate.features.networking_events',
            ],
        };
    }
}
