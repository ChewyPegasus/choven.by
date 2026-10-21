<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Class constraint for App\Entity\Order: the chosen dates must not be blocked or sold out.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AvailableDates extends Constraint
{
    public string $blockedMessage = 'order.error.date_blocked';
    public string $soldOutMessage = 'order.error.sold_out';
    public string $notEnoughMessage = 'order.error.not_enough_places';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
