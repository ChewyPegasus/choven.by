<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\RegistrationForm;
use App\Service\Registration\EmailVerificationService;
use App\Service\Registration\UserRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserRegistrationService $registrationService,
        private readonly EmailVerificationService $verificationService,
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_main');
        }
        
        $user = $this->registrationService->createUser();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validate user uniqueness
            $validationError = $this->registrationService->validateUserUniqueness($user);
            if ($validationError) {
                $this->addFlash('error', $translator->trans($validationError));

                return $this->redirectToRoute('app_register');
            }

            // Register user
            $this->registrationService->registerUser(
                $user, 
                $form->get('plainPassword')->getData()
            );

            // Send verification email
            $confirmUrl = $this->generateUrl(
                'app_verify_email',
                ['code' => $user->getConfirmationCode()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $emailSent = $this->registrationService->sendVerificationEmail(
                $user, 
                $confirmUrl, 
                $request->getLocale()
            );

            if ($emailSent) {
                $this->addFlash('success', $translator->trans('registration.success'));
            } else {
                // Queue email for retry
                $this->registrationService->queueVerificationEmail(
                    $user, 
                    $confirmUrl, 
                    $request->getLocale()
                );
                $this->addFlash('info', $translator->trans('registration.info.email_queued'));
            }

            return $this->redirectToRoute('app_main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/{code}', name: 'app_verify_email')]
    public function verifyUserEmail(
        string $code, 
        TranslatorInterface $translator,
    ): Response {
        $user = $this->verificationService->findUserByConfirmationCode($code);
        
        if (!$user) {
            $this->addFlash('error', $translator->trans('verification.invalid_link'));
            
            return $this->redirectToRoute('app_main');
        }

        if ($user->isConfirmed()) {
            $this->addFlash('info', $translator->trans('verification.already_confirmed'));
            
            return $this->redirectToRoute('app_login');
        }

        $this->verificationService->confirmUser($user);
        $this->addFlash('success', $translator->trans('verification.success'));

        return $this->redirectToRoute('app_login');
    }
}