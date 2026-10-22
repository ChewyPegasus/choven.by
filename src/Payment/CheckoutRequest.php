<?php

declare(strict_types=1);

namespace App\Payment;

/**
 * What the payment page needs to know besides the amount.
 */
final class CheckoutRequest
{
    public function __construct(
        public readonly string $description,
        public readonly string $customerEmail,
        public readonly string $locale,
        public readonly string $returnUrl,
        public readonly string $notifyUrl,
    ) {
    }
}
