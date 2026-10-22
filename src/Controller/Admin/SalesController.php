<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GiftCertificate;
use App\Entity\Payment;
use App\Entity\WaitlistEntry;
use App\Enum\GiftCertificateStatus;
use App\Enum\PaymentStatus;
use App\Form\Admin\GiftIssueType;
use App\Mailer\AppMailer;
use App\Payment\PaymentException;
use App\Repository\AuditLogRepository;
use App\Repository\GiftCertificateRepository;
use App\Repository\PaymentRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\GiftCertificateService;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Payments, gift certificates, the waitlist and the audit log.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SalesController extends AbstractController
{
    #[Route('/payments', name: 'app_admin_payments', methods: ['GET'])]
    public function payments(Request $request, PaymentRepository $payments, PaymentService $paymentService): Response
    {
        $status = PaymentStatus::tryFrom($request->query->getString('status'));

        return $this->render('admin/payment/index.html.twig', [
            'page' => $payments->findPage($status, $request->query->getInt('page', 1)),
            'status' => $status,
            'enabled' => $paymentService->isEnabled(),
            'month_total' => $payments->sumReceivedSince(new \DateTimeImmutable('first day of this month midnight')),
        ]);
    }

    #[Route('/payments/{id}/refund', name: 'app_admin_payment_refund', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refund(Payment $payment, Request $request, PaymentService $paymentService): Response
    {
        if (!$this->isCsrfTokenValid('refund-'.$payment->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
        } else {
            $amount = $request->getPayload()->getInt('amount') ?: null;

            try {
                $paymentService->refund($payment, $amount);
                $this->addFlash('success', 'admin.payments.flash.refunded');
            } catch (PaymentException) {
                $this->addFlash('error', 'admin.payments.flash.refund_failed');
            }
        }

        return null !== $payment->getOrder()
            ? $this->redirectToRoute('app_admin_order_edit', ['id' => $payment->getOrder()->getId()])
            : $this->redirectToRoute('app_admin_payments');
    }

    #[Route('/gift-certificates', name: 'app_admin_gift_certificates', methods: ['GET'])]
    public function giftCertificates(Request $request, GiftCertificateRepository $certificates): Response
    {
        $status = GiftCertificateStatus::tryFrom($request->query->getString('status'));
        $search = $request->query->getString('q');

        return $this->render('admin/gift/index.html.twig', [
            'page' => $certificates->findPage($status, $search, $request->query->getInt('page', 1)),
            'status' => $status,
            'q' => $search,
        ]);
    }

    #[Route('/gift-certificates/new', name: 'app_admin_gift_certificate_new', methods: ['GET', 'POST'])]
    public function issueGift(Request $request, GiftCertificateService $service): Response
    {
        $gift = (new GiftCertificate())->setLocale($request->getLocale());
        $form = $this->createForm(GiftIssueType::class, $gift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->issue($gift, (bool) $form->get('sendEmail')->getData());
            $this->addFlash('success', 'admin.gifts.flash.issued');

            return $this->redirectToRoute('app_admin_gift_certificates');
        }

        return $this->render('admin/gift/form.html.twig', ['form' => $form]);
    }

    #[Route('/gift-certificates/{id}/cancel', name: 'app_admin_gift_certificate_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelGift(GiftCertificate $gift, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('cancel-gift-'.$gift->getId(), $request->getPayload()->getString('_token'))) {
            $gift->cancel();
            $entityManager->flush();
            $this->addFlash('success', 'admin.gifts.flash.cancelled');
        }

        return $this->redirectToRoute('app_admin_gift_certificates');
    }

    #[Route('/gift-certificates/{id}/resend', name: 'app_admin_gift_certificate_resend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resendGift(GiftCertificate $gift, Request $request, AppMailer $mailer): Response
    {
        if ($this->isCsrfTokenValid('resend-gift-'.$gift->getId(), $request->getPayload()->getString('_token'))
            && GiftCertificateStatus::PENDING !== $gift->getStatus()) {
            $mailer->sendGiftCertificate($gift);
            $this->addFlash('success', 'admin.gifts.flash.resent');
        }

        return $this->redirectToRoute('app_admin_gift_certificates');
    }

    #[Route('/waitlist', name: 'app_admin_waitlist', methods: ['GET'])]
    public function waitlist(Request $request, WaitlistEntryRepository $entries, ClockInterface $clock): Response
    {
        return $this->render('admin/waitlist/index.html.twig', [
            'page' => $entries->findUpcomingPage($clock->now()->setTime(0, 0), $request->query->getInt('page', 1)),
        ]);
    }

    #[Route('/waitlist/{id}/delete', name: 'app_admin_waitlist_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteWaitlistEntry(WaitlistEntry $entry, Request $request, WaitlistEntryRepository $entries): Response
    {
        if ($this->isCsrfTokenValid('delete-waitlist-'.$entry->getId(), $request->getPayload()->getString('_token'))) {
            $entries->remove($entry);
            $this->addFlash('success', 'admin.waitlist.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_waitlist');
    }

    #[Route('/audit', name: 'app_admin_audit', methods: ['GET'])]
    public function audit(Request $request, AuditLogRepository $auditLog): Response
    {
        $type = $request->query->getString('type');
        $id = $request->query->getString('id');
        $user = $request->query->getString('user');

        return $this->render('admin/audit/index.html.twig', [
            'page' => $auditLog->findPage($type, $id, $user, $request->query->getInt('page', 1)),
            'types' => $auditLog->findEntityTypes(),
            'filter' => ['type' => $type, 'id' => $id, 'user' => $user],
        ]);
    }
}
