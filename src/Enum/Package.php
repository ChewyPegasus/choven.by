<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enum representing different types of packages offered for trips/services.
 *
 * Each case defines a specific package type and provides methods to retrieve
 * its translatable label, description, and a list of features.
 */
enum Package: string
{
    /**
     * Represents an "All-Inclusive" package.
     */
    case ALL_INCLUSIVE = 'all_inclusive';

    /**
     * Represents a "Minimum" package.
     */
    case MINIMUM = 'minimum';

    /**
     * Represents a "Rent Only" package.
     */
    case RENT_ONLY = 'rent_only';

    /**
     * Represents a "Corporate" package, typically for team events.
     */
    case CORPORATE = 'corporate';

    /**
     * Represents an "Other" or custom package type.
     */
    case OTHER = 'other';

    /**
     * Returns the translation key for the package's display label.
     *
     * This key can be used with a Symfony Translator to get the localized title.
     *
     * @return string The translation key for the package label.
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

    /**
     * Returns the translation key for the package's description.
     *
     * This key can be used with a Symfony Translator to get the localized description.
     *
     * @return string The translation key for the package description.
     */
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
    
    /**
     * Returns an array of translation keys for the package's features.
     *
     * Each string in the array is a translation key that can be used to
     * display individual features of the package.
     *
     * @return string[] An array of translation keys for package features.
     */
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