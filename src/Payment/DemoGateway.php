<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Payment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Fake payment page for development and tests: lets you "pay" or "decline" without a real provider.
 * Never available in production (see PaymentGateways).
 */
final class DemoGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getName(): string
    {
        return 'demo';
    }

    public function isTest(): bool
    {
        return true;
    }

    public function createCheckout(Payment $payment, CheckoutRequest $request): void
    {
        $payment
            ->setExternalId('demo-'.$payment->getPublicId())
            ->setRedirectUrl($this->urlGenerator->generate('app_payment_demo', [
                '_locale' => $request->locale,
                'publicId' => $payment->getPublicId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL))
            ->setTest(true);
    }

    public function fetchStatus(Payment $payment): ?PaymentResult
    {
        // The demo page sets the status directly.
        return null;
    }

    public function parseNotification(Request $request): ?string
    {
        return null;
    }

    public function refund(Payment $payment, int $amount, string $reason): void
    {
    }
}
