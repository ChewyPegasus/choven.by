<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Form\ContactRequestType;
use App\Mailer\AppMailer;
use App\Repository\ContactRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        ContactRequestRepository $repository,
        AppMailer $mailer,
        RateLimiterFactoryInterface $contactLimiter,
    ): Response {
        $contactRequest = new ContactRequest();
        $form = $this->createForm(ContactRequestType::class, $contactRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$contactLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'flash.too_many_requests');

                return $this->redirectToRoute('app_contact');
            }

            $contactRequest->setLocale($request->getLocale());
            $repository->save($contactRequest);
            $mailer->sendContactRequestNotification($contactRequest);

            $this->addFlash('success', 'contact.flash.sent');

            return $this->redirectToRoute('app_main', ['_fragment' => 'contact']);
        }

        return $this->render('contact/index.html.twig', [
            'contact_form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
