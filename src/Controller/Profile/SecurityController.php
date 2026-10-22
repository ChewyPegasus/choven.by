<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Entity\User;
use App\Messenger\SubscriptionLinker;
use App\Messenger\TelegramBot;
use App\Messenger\ViberBot;
use App\Repository\MessengerSubscriptionRepository;
use App\Service\TwoFactorManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Account security (two-factor authentication) and notifications in messengers.
 */
#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class SecurityController extends AbstractController
{
    public function __construct(private readonly TwoFactorManager $twoFactor)
    {
    }

    #[Route('/security', name: 'app_profile_security', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        Request $request,
        SubscriptionLinker $linker,
        MessengerSubscriptionRepository $subscriptions,
        TelegramBot $telegram,
        ViberBot $viber,
    ): Response {
        $payload = $linker->payloadForUser($user);
        $setupInProgress = !$user->isTotpAuthenticationEnabled() && null !== $user->getTotpSecret();
        // Backup codes are passed once through the session right after they were generated.
        $session = $request->getSession();
        $backupCodes = $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag()->get('backup_codes') : [];

        $response = $this->render('profile/security.html.twig', [
            'setup' => $setupInProgress,
            'qr_code' => $setupInProgress ? $this->twoFactor->qrCode($user) : null,
            'backup_codes' => $backupCodes,
            'messengers' => [
                'telegram' => $telegram->canLinkCustomers() ? $telegram->getDeepLink($payload) : null,
                'viber' => $viber->isEnabled() ? $viber->getDeepLink($payload) : null,
            ],
            'subscriptions' => $subscriptions->findForUser($user),
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/security/2fa/start', name: 'app_profile_2fa_start', methods: ['POST'])]
    public function start(#[CurrentUser] User $user, Request $request): Response
    {
        if ($this->isCsrfTokenValid('2fa-start', $request->getPayload()->getString('_token'))) {
            $this->twoFactor->start($user);
        }

        return $this->redirectToRoute('app_profile_security', ['_fragment' => 'two-factor']);
    }

    #[Route('/security/2fa/confirm', name: 'app_profile_2fa_confirm', methods: ['POST'])]
    public function confirm(#[CurrentUser] User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('2fa-confirm', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute('app_profile_security');
        }

        $codes = $this->twoFactor->confirm($user, $request->getPayload()->getString('code'));
        if (null === $codes) {
            $this->addFlash('error', 'twofa.flash.wrong_code');
        } else {
            $this->addFlash('success', 'twofa.flash.enabled');
            $this->addFlash('backup_codes', implode(' ', $codes));
        }

        return $this->redirectToRoute('app_profile_security', ['_fragment' => 'two-factor']);
    }

    #[Route('/security/2fa/backup-codes', name: 'app_profile_2fa_backup_codes', methods: ['POST'])]
    public function backupCodes(#[CurrentUser] User $user, Request $request): Response
    {
        if ($user->isTotpAuthenticationEnabled() && $this->isCsrfTokenValid('2fa-backup', $request->getPayload()->getString('_token'))) {
            $this->addFlash('backup_codes', implode(' ', $this->twoFactor->regenerateBackupCodes($user)));
        }

        return $this->redirectToRoute('app_profile_security', ['_fragment' => 'two-factor']);
    }

    #[Route('/security/2fa/disable', name: 'app_profile_2fa_disable', methods: ['POST'])]
    public function disable(#[CurrentUser] User $user, Request $request, UserPasswordHasherInterface $hasher): Response
    {
        if (!$this->isCsrfTokenValid('2fa-disable', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
        } elseif (!$hasher->isPasswordValid($user, $request->getPayload()->getString('password'))) {
            $this->addFlash('error', 'twofa.flash.wrong_password');
        } else {
            $this->twoFactor->disable($user);
            $this->addFlash('success', 'twofa.flash.disabled');
        }

        return $this->redirectToRoute('app_profile_security', ['_fragment' => 'two-factor']);
    }

    #[Route('/security/trusted/forget', name: 'app_profile_forget_devices', methods: ['POST'])]
    public function forgetDevices(#[CurrentUser] User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('forget-devices', $request->getPayload()->getString('_token'))) {
            $user->forgetTrustedDevices();
            $entityManager->flush();
            $this->addFlash('success', 'twofa.flash.devices_forgotten');
        }

        return $this->redirectToRoute('app_profile_security', ['_fragment' => 'two-factor']);
    }

    #[Route('/security/messengers/{id}/disconnect', name: 'app_profile_messenger_disconnect', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function disconnect(int $id, #[CurrentUser] User $user, Request $request, MessengerSubscriptionRepository $subscriptions): Response
    {
        $subscription = $subscriptions->find($id);
        if (null !== $subscription && $subscription->getUser()?->getId() === $user->getId()
            && $this->isCsrfTokenValid('disconnect-'.$id, $request->getPayload()->getString('_token'))) {
            $subscriptions->remove($subscription);
            $this->addFlash('success', 'messenger.flash.disconnected');
        }

        return $this->redirectToRoute('app_profile_security', ['_fragment' => 'messengers']);
    }
}
