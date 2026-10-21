<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Mailer\AppMailer;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Email confirmation with signed, expiring links (no tokens stored in the database).
 */
class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $helper,
        private readonly AppMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    public function sendConfirmation(User $user): void
    {
        $signature = $this->helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId(), '_locale' => $user->getLocale()],
        );

        $hours = (int) ceil(($signature->getExpiresAt()->getTimestamp() - time()) / 3600);
        $this->mailer->sendEmailVerification($user, $signature->getSignedUrl(), max(1, $hours));
    }

    /**
     * @throws VerifyEmailExceptionInterface when the link is invalid or expired
     */
    public function confirm(Request $request, User $user): void
    {
        $this->helper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());

        $user->setIsVerified(true);
        $this->entityManager->flush();

        // Bookings made as a guest with this email now show up in the personal account.
        $this->orderRepository->attachGuestOrdersToUser($user);
    }
}
