<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContactRequest;
use App\Repository\ContactRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Inbox of "call me back" requests.
 */
#[Route('/admin/requests')]
#[IsGranted('ROLE_ADMIN')]
final class ContactRequestController extends AbstractController
{
    public function __construct(private readonly ContactRequestRepository $requests)
    {
    }

    #[Route('', name: 'app_admin_contact_requests', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = $request->query->getString('show', 'open');
        $processed = match ($filter) {
            'all' => null,
            'done' => true,
            default => false,
        };

        return $this->render('admin/contact_request/index.html.twig', [
            'page' => $this->requests->findPage($processed, $request->query->getInt('page', 1)),
            'show' => $filter,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_contact_request_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(ContactRequest $contactRequest, Request $request): Response
    {
        if ($this->isCsrfTokenValid('contact-'.$contactRequest->getId(), $request->getPayload()->getString('_token'))) {
            $contactRequest->setIsProcessed(!$contactRequest->isProcessed());
            $this->requests->save($contactRequest);
        }

        return $this->redirectToRoute('app_admin_contact_requests', ['show' => $request->getPayload()->getString('back', 'open')]);
    }

    #[Route('/{id}/delete', name: 'app_admin_contact_request_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(ContactRequest $contactRequest, Request $request): Response
    {
        if ($this->isCsrfTokenValid('contact-'.$contactRequest->getId(), $request->getPayload()->getString('_token'))) {
            $this->requests->remove($contactRequest);
            $this->addFlash('success', 'admin.requests.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_contact_requests', ['show' => $request->getPayload()->getString('back', 'open')]);
    }
}
