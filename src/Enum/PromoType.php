<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum PromoType: string implements TranslatableInterface
{
    /** Value is a percentage of the price. */
    case PERCENT = 'percent';
    /** Value is an amount in BYN. */
    case FIXED = 'fixed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('promo.type.'.$this->value, [], 'messages', $locale);
    }
}
