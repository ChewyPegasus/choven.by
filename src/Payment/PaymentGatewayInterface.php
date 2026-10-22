<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Payment;
use Symfony\Component\HttpFoundation\Request;

interface PaymentGatewayInterface
{
    public function getName(): string;

    public function isTest(): bool;

    /**
     * Registers the payment at the provider; sets the external id and the URL of the payment page.
     *
     * @throws PaymentException
     */
    public function createCheckout(Payment $payment, CheckoutRequest $request): void;

    /**
     * Asks the provider for the current status. Null when the provider cannot tell (yet).
     *
     * @throws PaymentException
     */
    public function fetchStatus(Payment $payment): ?PaymentResult;

    /**
     * Extracts our payment id from a server-to-server notification; null when the request is not valid.
     */
    public function parseNotification(Request $request): ?string;

    /**
     * @throws PaymentException
     */
    public function refund(Payment $payment, int $amount, string $reason): void;
}
