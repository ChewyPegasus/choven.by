<?php

declare(strict_types=1);

namespace App\Controller\Webhook;

use App\Payment\PaymentGateways;
use App\Repository\PaymentRepository;
use App\Service\PaymentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Server-to-server notifications of the payment provider.
 *
 * The body only tells which payment changed: the status itself is fetched from the provider's API.
 */
#[AsController]
final class PaymentWebhookController
{
    #[Route('/payment/notify/{provider}', name: 'app_payment_notify', requirements: ['provider' => '[a-z]+'], methods: ['POST'])]
    public function __invoke(
        string $provider,
        Request $request,
        PaymentGateways $gateways,
        PaymentRepository $payments,
        PaymentService $paymentService,
        LoggerInterface $logger,
    ): Response {
        $gateway = $gateways->byName($provider);
        if (null === $gateway) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $publicId = $gateway->parseNotification($request);
        $payment = null !== $publicId ? $payments->findOneByPublicId($publicId) : null;
        if (null === $payment || $payment->getProvider() !== $provider) {
            $logger->warning('Payment notification for an unknown payment from {ip}', ['ip' => $request->getClientIp()]);

            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $paymentService->refresh($payment);

        // 200 tells the provider to stop resending; it retries on anything else.
        return new Response('OK', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
}
