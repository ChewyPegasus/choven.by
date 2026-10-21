<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Form\ResetPasswordRequestType;
use App\Repository\UserRepository;
use App\Service\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        RateLimiterFactoryInterface $registrationLimiter,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_profile_orders');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'flash.too_many_requests');

                return $this->redirectToRoute('app_register');
            }

            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $user->setLocale($request->getLocale());
            $this->users->save($user);

            $this->emailVerifier->sendConfirmation($user);
            $request->getSession()->set('registration_email', $user->getEmail());

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render('registration/register.html.twig', ['form' => $form]);
    }

    #[Route('/register/check-email', name: 'app_register_check_email', methods: ['GET'])]
    public function checkEmail(Request $request): Response
    {
        return $this->render('registration/check_email.html.twig', [
            'email' => $request->getSession()->get('registration_email'),
        ]);
    }

    /**
     * Target of the signed link from the confirmation email. Works without being signed in.
     */
    #[Route('/verify', name: 'app_verify_email', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        $user = $this->users->find($request->query->getInt('id'));
        if (null === $user) {
            $this->addFlash('error', 'verification.flash.invalid');

            return $this->redirectToRoute('app_verify_resend');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'verification.flash.already');

            return $this->redirectToRoute('app_login');
        }

        try {
            $this->emailVerifier->confirm($request, $user);
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', new TranslatableMessage($e->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_verify_resend');
        }

        $this->addFlash('success', 'verification.flash.success');

        return $this->redirectToRoute('app_login');
    }

    /**
     * Sends a fresh confirmation link. The answer is the same whether the email exists or not.
     */
    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['GET', 'POST'])]
    public function resend(Request $request, RateLimiterFactoryInterface $verificationLimiter): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $limit = $verificationLimiter->create(mb_strtolower($email).'|'.$request->getClientIp())->consume();

            $user = $this->users->findOneByEmail($email);
            if ($limit->isAccepted() && null !== $user && !$user->isVerified()) {
                $this->emailVerifier->sendConfirmation($user);
            }

            $request->getSession()->set('registration_email', $email);

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render('registration/resend.html.twig', ['form' => $form]);
    }
}
