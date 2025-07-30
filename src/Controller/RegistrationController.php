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

/**
 * Controller for handling user registration and email verification.
 *
 * This controller provides endpoints for new user sign-up and
 * for confirming email addresses via a verification link.
 */
final class RegistrationController extends AbstractController
{
    /**
     * Constructs a new RegistrationController instance.
     *
     * @param UserRegistrationService $registrationService The service for user registration logic.
     * @param EmailVerificationService $verificationService The service for email verification logic.
     */
    public function __construct(
        private readonly UserRegistrationService $registrationService,
        private readonly EmailVerificationService $verificationService,
    ) {
    }

    /**
     * Handles new user registration.
     *
     * Displays the registration form. Upon valid submission, it attempts to
     * register the user, validate uniqueness, and send a verification email.
     * If email sending fails, it queues the email for retry.
     *
     * @param Request $request The current HTTP request.
     * @param TranslatorInterface $translator The translator service for internationalization.
     * @return Response The HTTP response for the registration form or a redirect.
     */
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        // Redirect authenticated users away from the registration page
        if ($this->getUser()) {
            return $this->redirectToRoute('app_main');
        }
        
        // Create a new user entity and the registration form
        $user = $this->registrationService->createUser();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validate user uniqueness (e.g., email already exists)
            $validationError = $this->registrationService->validateUserUniqueness($user);
            if ($validationError) {
                $this->addFlash('error', $translator->trans($validationError));

                return $this->redirectToRoute('app_register');
            }

            // Register the user (persist to database, hash password)
            $this->registrationService->registerUser(
                $user, 
                $form->get('plainPassword')->getData() // Get plain password from form
            );

            // Generate the absolute URL for email verification
            $confirmUrl = $this->generateUrl(
                'app_verify_email',
                ['code' => $user->getConfirmationCode()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Attempt to send the verification email
            $emailSent = $this->registrationService->sendVerificationEmail(
                $user, 
                $confirmUrl, 
                $request->getLocale()
            );

            if ($emailSent) {
                $this->addFlash('success', $translator->trans('registration.success'));
            } else {
                // If email sending fails, queue it for retry
                $this->registrationService->queueVerificationEmail(
                    $user, 
                    $confirmUrl, 
                    $request->getLocale()
                );
                $this->addFlash('info', $translator->trans('registration.info.email_queued'));
            }

            return $this->redirectToRoute('app_main'); // Redirect after successful registration
        }

        // Render the registration form for initial display or if validation fails
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * Verifies a user's email address using a confirmation code.
     *
     * This method is accessed via a link sent in the verification email.
     * It finds the user by the provided code, confirms their account,
     * and provides feedback via flash messages.
     *
     * @param string $code The unique confirmation code for email verification.
     * @param TranslatorInterface $translator The translator service for internationalization.
     * @return Response A RedirectResponse to the main page or login page.
     */
    #[Route('/verify/{code}', name: 'app_verify_email')]
    public function verifyUserEmail(
        string $code, 
        TranslatorInterface $translator,
    ): Response {
        // Find user by the confirmation code
        $user = $this->verificationService->findUserByConfirmationCode($code);
        
        if (!$user) {
            $this->addFlash('error', $translator->trans('verification.invalid_link'));
            
            return $this->redirectToRoute('app_main');
        }

        // Check if the user is already confirmed
        if ($user->isConfirmed()) {
            $this->addFlash('info', $translator->trans('verification.already_confirmed'));
            
            return $this->redirectToRoute('app_login');
        }

        // Confirm the user's email
        $this->verificationService->confirmUser($user);
        $this->addFlash('success', $translator->trans('verification.success'));

        return $this->redirectToRoute('app_login'); // Redirect to login after successful verification
    }
}