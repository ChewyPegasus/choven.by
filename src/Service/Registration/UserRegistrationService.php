<?php

declare(strict_types=1);

namespace App\Service\Registration;

use App\Entity\User;
use App\Enum\EmailTemplate;
use App\Enum\Role;
use App\Factory\EmailFactory;
use App\Factory\UserFactory;
use App\Repository\EmailQueueRepository;
use App\Repository\UserRepository;
use App\Service\Messaging\Producer\Producer;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailQueueRepository $emailQueueRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Producer $producer,
        private readonly EmailFactory $emailFactory,
        private readonly LoggerInterface $logger,
        private readonly UserFactory $userFactory,
        private readonly string $registrationTopic,
    ) {
    }

    public function createUser(): User
    {
        return $this->userFactory->create();
    }

    public function validateUserUniqueness(User $user): ?string
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return 'registration.email.already_exists';
        }

        $existingUserByPhone = $this->userRepository->findOneBy(['phone' => $user->getPhone()]);
        if ($existingUserByPhone) {
            return 'registration.phone.already_used';
        }

        return null;
    }

    public function registerUser(User $user, string $plainPassword): void
    {
        $confirmationCode = bin2hex(random_bytes(10));
        $user->setConfirmationCode($confirmationCode);
        $user->setIsConfirmed(false);
        $user->setRoles([Role::USER]);

        $user->setPassword(
            $this->hasher->hashPassword($user, $plainPassword)
        );

        $this->userRepository->save($user);
    }

    public function sendVerificationEmail(User $user, string $confirmUrl, string $locale): bool
    {
        try {
            $this->producer->produce(
                $this->registrationTopic,
                $this->emailFactory->createDTO(
                    EmailTemplate::VERIFICATION,
                    [
                        'user' => $user,
                        'confirmUrl' => $confirmUrl,
                        'locale' => $locale,
                    ]
                ),
                'user_' . $user->getId(),
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Kafka publishing failed for registration email: ' . $e->getMessage(), ['exception' => $e]);
            
            return false;
        }
    }

    public function queueVerificationEmail(User $user, string $confirmUrl, string $locale): void
    {
        $emailQueue = $this->emailFactory->createEmailQueue(
            EmailTemplate::VERIFICATION->value,
            [
                'user' => $user->getId(),
                'confirmUrl' => $confirmUrl,
            ],
            $locale,
        );
        
        $this->emailQueueRepository->save($emailQueue);
    }
}