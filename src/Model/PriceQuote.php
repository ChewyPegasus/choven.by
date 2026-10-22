<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Price breakdown of a booking, in whole BYN.
 */
final class PriceQuote
{
    /**
     * @param int|null                                                         $base   trip price; null when the tour has no public price
     * @param list<array{extra: \App\Entity\Extra, quantity: int, total: int}> $extras
     */
    public function __construct(
        public readonly ?int $base,
        public readonly array $extras,
        public readonly int $extrasTotal,
        public readonly int $discount,
        public readonly int $giftAmount,
        public readonly ?int $total,
        public readonly ?int $deposit,
    ) {
    }

    public function hasPrice(): bool
    {
        return null !== $this->total;
    }
}
