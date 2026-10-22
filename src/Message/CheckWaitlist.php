<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Places may have become free in this period (Y-m-d, inclusive): tell the people on the waitlist.
 * Without dates (the daily scheduled run) the next 90 days are checked, which catches changes
 * of the capacity or of the blocked dates in the settings.
 */
final class CheckWaitlist
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
    }
}
