<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enum representing various rivers.
 *
 * Each case corresponds to a specific river and provides a method to retrieve
 * its translatable label for display purposes.
 */
enum River: string
{
    /**
     * Represents the Svisloch river.
     */
    case SVISLOCH = 'svisloch';

    /**
     * Represents the Isloch river.
     */
    case ISLOCH = 'isloch';

    /**
     * Represents the Berezina river.
     */
    case BEREZINA = 'berezina';

    /**
     * Represents the Neman river.
     */
    case NEMAN = 'neman';

    /**
     * Represents the Narochanka river.
     */
    case NAROCHANKA = 'narochanka';

    /**
     * Represents the Stracha river.
     */
    case STRACHA = 'stracha';

    /**
     * Represents the Saryanka river.
     */
    case SARYANKA = 'saryanka';

    /**
     * Represents the Sluch river.
     */
    case SLUCH = 'sluch';

    /**
     * Represents the Viliya river.
     */
    case VILIYA = 'viliya';

    /**
     * Represents the Sula river.
     */
    case SULA = 'sula';

    /**
     * Represents the Usa river.
     */
    case USA = 'usa';

    /**
     * Represents the Smerd river.
     */
    case SMERD = 'smerd';

    /**
     * Represents an "Other" or unspecified river.
     */
    case OTHER = 'other';

    /**
     * Returns the translation key for the river's display label.
     *
     * This key can be used with a Symfony Translator to get the localized name of the river.
     *
     * @return string The translation key for the river label.
     */
    public function getLabel(): string
    {
        return match($this) {
            self::SVISLOCH => 'river.svisloch',
            self::ISLOCH => 'river.isloch',
            self::BEREZINA => 'river.berezina',
            self::NEMAN => 'river.neman',
            self::NAROCHANKA => 'river.narochanka',
            self::STRACHA => 'river.stracha',
            self::SARYANKA => 'river.saryanka',
            self::SLUCH => 'river.sluch',
            self::VILIYA => 'river.viliya',
            self::SULA => 'river.sula',
            self::USA => 'river.usa',
            self::SMERD => 'river.smerd',
            self::OTHER => 'river.other',
        };
    }
}