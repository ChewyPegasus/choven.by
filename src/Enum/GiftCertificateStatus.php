<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum GiftCertificateStatus: string implements TranslatableInterface
{
    /** Ordered on the site, waiting for the online payment. */
    case PENDING = 'pending';
    case ACTIVE = 'active';
    /** The whole amount has been spent. */
    case USED = 'used';
    case CANCELLED = 'cancelled';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('gift.status.'.$this->value, [], 'messages', $locale);
    }
}
