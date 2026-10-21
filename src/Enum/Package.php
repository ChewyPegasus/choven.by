<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service level of a trip. Drives the popup on the landing page and the booking form.
 */
enum Package: string implements TranslatableInterface
{
    case ALL_INCLUSIVE = 'all_inclusive';
    case MINIMUM = 'minimum';
    case RENT_ONLY = 'rent_only';
    case CORPORATE = 'corporate';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return 'package.'.$this->value.'.title';
    }

    public function getDescription(): string
    {
        return 'package.'.$this->value.'.description';
    }

    /**
     * @return list<string> translation keys
     */
    public function getFeatures(): array
    {
        return match ($this) {
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
                'package.rent_only.features.equipment',
            ],
            self::CORPORATE => [
                'package.corporate.features.team_building',
                'package.corporate.features.equipment',
                'package.corporate.features.instructor',
                'package.corporate.features.food',
                'package.corporate.features.transport',
                'package.corporate.features.group_activities',
            ],
            self::OTHER => [
                'package.other.features.custom',
            ],
        };
    }

    /**
     * Packages shown as cards with a details popup on the landing page.
     *
     * @return list<self>
     */
    public static function showcase(): array
    {
        return [self::ALL_INCLUSIVE, self::MINIMUM, self::RENT_ONLY, self::CORPORATE];
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getLabel(), [], 'messages', $locale);
    }
}
