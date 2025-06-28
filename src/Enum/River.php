<?php

declare(strict_types=1);

namespace App\Enum;

enum River: string
{
    case SVISLOCH = 'svisloch';
    case ISLOCH = 'isloch';
    case BEREZINA = 'berezina';
    case NEMAN = 'neman';
    case NAROCHANKA = 'narochanka';
    case STRACHA = 'stracha';
    case SARYANKA = 'saryanka';
    case SLUCH = 'sluch';
    case VILIYA = 'viliya';
    case SULA = 'sula';
    case USA = 'usa';
    case SMERD = 'smerd';
    case OTHER = 'other';

    /**
     * Returning key for translation
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
