<?php

namespace App\Enum;

enum River: string {
    case SVISLOCH = 'svisloch';
    case ISLOCH = 'isloch';
    case UZLYANKA = 'uzlyanka';
    case NAROCHANKA = 'narochanka';
    case STRACHA = 'stracha';
    case SARYANKA = 'saryanka';
    case SLUCH = 'sluch';
    case VILIYA = 'viliya';
    case SULA = 'sula';
    case USA = 'usa';
    case SMERD = 'smerd';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match($this) {
            self::SVISLOCH => 'Свислочь',
            self::ISLOCH => 'Ислочь',
            self::UZLYANKA => 'Узлянка',
            self::NAROCHANKA => 'Нарочанка',
            self::STRACHA => 'Страча',
            self::SARYANKA => 'Сарьянка',
            self::SLUCH => 'Случь',
            self::VILIYA => 'Вилия',
            self::SULA => 'Сула',
            self::USA => 'Уса',
            self::SMERD => 'Смердь',
            self::OTHER => 'Другая',
        };
    }
}