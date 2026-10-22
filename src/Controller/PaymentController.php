<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Payment\PaymentGateways;
use App\Payment\PaymentResult;
use App\Repository\PaymentRepository;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The customer comes back from the payment page; plus the fake payment page used in development.
 */
#[Route('/payment')]
final class PaymentController extends AbstractController
{
    private const ID = '[a-f0-9]{32}';

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentService $paymentService,
    ) {
    }

    #[Route('/{publicId}/return', name: 'app_payment_return', requirements: ['publicId' => self::ID], methods: ['GET'])]
    public function return(string $publicId): Response
    {
        $payment = $this->find($publicId);
        $this->paymentService->refresh($payment);

        $this->addFlash(...match ($payment->getStatus()) {
            PaymentStatus::SUCCEEDED, PaymentStatus::REFUNDED => ['success', 'payment.flash.succeeded'],
            PaymentStatus::PENDING => ['info', 'payment.flash.pending'],
            default => ['error', 'payment.flash.failed'],
        });

        return $this->redirectToSubject($payment);
    }

    /**
     * Stand-in for the bank's payment page (PAYMENT_PROVIDER=demo, never in production).
     */
    #[Route('/demo/{publicId}', name: 'app_payment_demo', requirements: ['publicId' => self::ID], methods: ['GET', 'POST'])]
    public function demo(string $publicId, Request $request, PaymentGateways $gateways): Response
    {
        $payment = $this->find($publicId);
        if (null === $gateways->byName('demo') || 'demo' !== $payment->getProvider()) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST') && $payment->isPending()) {
            if (!$this->isCsrfTokenValid('demo-pay-'.$payment->getPublicId(), $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $this->paymentService->apply($payment, 'pay' === $request->getPayload()->getString('result')
                ? new PaymentResult(PaymentStatus::SUCCEEDED, 'demo-'.bin2hex(random_bytes(6)))
                : new PaymentResult(PaymentStatus::FAILED, null, 'Declined on the demo page'));

            return $this->redirectToRoute('app_payment_return', ['publicId' => $publicId]);
        }

        $response = $this->render('payment/demo.html.twig', ['payment' => $payment]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private function find(string $publicId): Payment
    {
        return $this->payments->findOneByPublicId($publicId) ?? throw $this->createNotFoundException();
    }

    private function redirectToSubject(Payment $payment): Response
    {
        if (null !== $payment->getOrder()) {
            return $this->redirectToRoute('app_order_show', ['token' => $payment->getOrder()->getAccessToken()]);
        }
        if (null !== $payment->getGiftCertificate()) {
            return $this->redirectToRoute('app_gift_show', ['token' => $payment->getGiftCertificate()->getToken()]);
        }

        return $this->redirectToRoute('app_main');
    }
}
