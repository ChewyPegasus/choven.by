<?php

declare(strict_types=1);

namespace App\Enum;

enum MessengerChannel: string
{
    case TELEGRAM = 'telegram';
    case VIBER = 'viber';

    public function getLabel(): string
    {
        return match ($this) {
            self::TELEGRAM => 'Telegram',
            self::VIBER => 'Viber',
        };
    }
}
