<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum OrderStatus: string implements TranslatableInterface
{
    case NEW = 'new';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return 'order.status.'.$this->value;
    }

    /**
     * Whether the booking still occupies a slot in the schedule.
     */
    public function isActive(): bool
    {
        return self::NEW === $this || self::CONFIRMED === $this;
    }

    public function isCancellableByCustomer(): bool
    {
        return $this->isActive();
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::NEW, self::CONFIRMED];
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getLabel(), [], 'messages', $locale);
    }
}
