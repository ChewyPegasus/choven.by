<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * How the price of an add-on service is counted.
 */
enum ExtraUnit: string implements TranslatableInterface
{
    /** Once for the whole booking (a photographer). */
    case PER_ORDER = 'order';
    /** For every participant (a transfer from Minsk). */
    case PER_PERSON = 'person';
    /** The customer chooses how many (tents, sleeping bags). */
    case PER_ITEM = 'item';

    public function hasQuantity(): bool
    {
        return self::PER_ITEM === $this;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('extra.unit.'.$this->value, [], 'messages', $locale);
    }
}
