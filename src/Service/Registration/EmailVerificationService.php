<?php

declare(strict_types=1);

namespace App\Service\Registration;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class EmailVerificationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findUserByConfirmationCode(string $code): ?User
    {
        return $this->userRepository->findOneBy(['confirmationCode' => $code]);
    }

    public function confirmUser(User $user): void
    {
        $user->setIsConfirmed(true);
        $user->setConfirmationCode(null);
        $this->entityManager->flush();
    }
}