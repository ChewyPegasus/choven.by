<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GiftCertificate;
use App\Enum\GiftCertificateStatus;
use App\Form\GiftCertificateType;
use App\Payment\PaymentException;
use App\Repository\GiftCertificateRepository;
use App\Service\GiftCertificatePdf;
use App\Service\GiftCertificateService;
use App\Service\PaymentService;
use App\Service\SiteSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gift certificates: buying online and the printable certificate.
 */
#[Route('/gift')]
final class GiftController extends AbstractController
{
    private const TOKEN = '[a-f0-9]{32}';

    #[Route('', name: 'app_gift', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SiteSettings $settings,
        PaymentService $payments,
        GiftCertificateService $certificates,
        RateLimiterFactoryInterface $giftLimiter,
    ): Response {
        $canBuy = $settings->isGiftCertificatesEnabled() && $payments->isEnabled();
        $gift = (new GiftCertificate())->setAmount(GiftCertificateType::PRESETS[1]);
        $form = $this->createForm(GiftCertificateType::class, $gift);
        $form->handleRequest($request);

        if ($canBuy && $form->isSubmitted() && $form->isValid()) {
            if (!$giftLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'flash.too_many_requests');

                return $this->redirectToRoute('app_gift');
            }

            $certificates->order($gift, $request->getLocale());

            try {
                return $this->redirect($payments->startForGiftCertificate($gift));
            } catch (PaymentException) {
                $this->addFlash('error', 'payment.flash.unavailable');
            }
        }

        return $this->render('gift/index.html.twig', [
            'form' => $form,
            'can_buy' => $canBuy,
            'presets' => GiftCertificateType::PRESETS,
        ]);
    }

    #[Route('/{token}', name: 'app_gift_show', requirements: ['token' => self::TOKEN], methods: ['GET'])]
    public function show(string $token, GiftCertificateRepository $certificates): Response
    {
        $gift = $certificates->findOneByToken($token) ?? throw $this->createNotFoundException();

        $response = $this->render('gift/show.html.twig', ['gift' => $gift]);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();

        return $response;
    }

    #[Route('/{token}/certificate.pdf', name: 'app_gift_pdf', requirements: ['token' => self::TOKEN], methods: ['GET'])]
    public function pdf(string $token, GiftCertificateRepository $certificates, GiftCertificatePdf $renderer): Response
    {
        $gift = $certificates->findOneByToken($token);
        if (null === $gift || GiftCertificateStatus::PENDING === $gift->getStatus() || GiftCertificateStatus::CANCELLED === $gift->getStatus()) {
            throw $this->createNotFoundException();
        }

        $pdf = $renderer->render($gift) ?? throw new \RuntimeException('The certificate could not be rendered.');

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $gift->getCode().'.pdf'));
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();

        return $response;
    }
}
