<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\EmailType;
use App\Enum\Role;
use App\Factory\EmailFactory;
use App\Form\RegistrationForm;
use App\Repository\UserRepository;
use App\Service\Sending\EmailSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailSender $sender,
        private EmailFactory $emailFactory,
        private LoggerInterface $logger,
        private UserRepository $userRepository,
    ) {}

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $manager,
        TranslatorInterface $translator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_main');
        }
        
        $user = new User();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', $translator->trans('registration.email.already_exists'));
                
                return $this->redirectToRoute('app_register');
            }

            $confirmationCode = bin2hex(random_bytes(10));
            $user->setConfirmationCode($confirmationCode);
            $user->setIsConfirmed(false);
            $user->setRoles([Role::USER->value]);

            $user->setPassword(
                $hasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $manager->persist($user);
            $manager->flush();

            // Отправка письма с подтверждением
            try {
                $this->sender->send($this->emailFactory->createDTO(
                    EmailType::VERIFICATION,
                    [
                        'user' => $user,
                        'confirmUrl' => $this->generateUrl(
                            'app_verify_email',
                            ['code' => $user->getConfirmationCode()],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        )
                    ]
                ));
                $this->addFlash('success', $translator->trans('registration.success'));
            } catch (\Exception $e) {
                $this->logger->error('Welcome email sending failed: ' . $e->getMessage(), ['exception' => $e]);
                $this->addFlash('warning', $translator->trans('registration.email.failed'));
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
        EntityManagerInterface $manager,
    ): Response {
        $user = $this->userRepository->findOneBy([
            'confirmationCode' => $code,
        ]);
        
        if (!$user) {
            $this->addFlash('error', $translator->trans('verification.invalid_link'));
            
            return $this->redirectToRoute('app_main');
        }

        // Если пользователь уже подтвержден
        if ($user->isConfirmed()) {
            $this->addFlash('info', $translator->trans('verification.already_confirmed'));
            
            return $this->redirectToRoute('app_login');
        }

        $user->setIsConfirmed(true);
        $user->setConfirmationCode(null);
        $manager->flush();

        $this->addFlash('success', $translator->trans('verification.success'));

        return $this->redirectToRoute('app_login');
    }
}
