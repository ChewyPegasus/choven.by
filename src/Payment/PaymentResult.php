<?php

declare(strict_types=1);

namespace App\Payment;

use App\Enum\PaymentStatus;

/**
 * Status of a payment as reported by the provider.
 */
final class PaymentResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $message = null,
    ) {
    }
}
