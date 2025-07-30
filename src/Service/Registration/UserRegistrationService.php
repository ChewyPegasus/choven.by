<?php

declare(strict_types=1);

namespace App\Service\Registration;

use App\Entity\User;
use App\Enum\EmailTemplate;
use App\Enum\Role;
use App\Factory\EmailFactory;
use App\Factory\UserFactory;
use App\Repository\Interfaces\EmailQueueRepositoryInterface;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Service\Messaging\Producer\Producer;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for handling user registration logic.
 *
 * This service encapsulates the business logic for creating new users,
 * validating their uniqueness, hashing passwords, assigning default roles,
 * and sending/queuing email verification messages.
 */
class UserRegistrationService
{
    /**
     * Constructs a new UserRegistrationService instance.
     *
     * @param UserRepositoryInterface $userRepository The repository for managing User entities.
     * @param EmailQueueRepositoryInterface $emailQueueRepository The repository for managing EmailQueue entities.
     * @param UserPasswordHasherInterface $hasher The password hasher service.
     * @param Producer $producer The Kafka producer service for sending messages.
     * @param EmailFactory $emailFactory The factory for creating email-related DTOs and entities.
     * @param LoggerInterface $logger The logger instance for recording activities and errors.
     * @param UserFactory $userFactory The factory for creating User entities.
     * @param string $registrationTopic The Kafka topic name for registration-related messages.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailQueueRepositoryInterface $emailQueueRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Producer $producer,
        private readonly EmailFactory $emailFactory,
        private readonly LoggerInterface $logger,
        private readonly UserFactory $userFactory,
        private readonly string $registrationTopic,
    ) {
    }

    /**
     * Creates a new User entity instance.
     *
     * This method delegates the creation of a new User object to the `UserFactory`.
     *
     * @return User A new User entity.
     */
    public function createUser(): User
    {
        return $this->userFactory->create();
    }

    /**
     * Validates the uniqueness of a user's email and phone number.
     *
     * Checks if a user with the same email or phone number already exists in the database.
     *
     * @param User $user The User entity to validate.
     * @return string|null A translation key for an error message if a duplicate is found, otherwise null.
     */
    public function validateUserUniqueness(User $user): ?string
    {
        // Check for existing user by email
        $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser && $existingUser->getId() !== $user->getId()) { // Ensure it's not the same user on update
            return 'registration.email.already_exists';
        }

        // Check for existing user by phone number
        // Only check if phone number is provided, as it's nullable
        if ($user->getPhone() !== null) {
            $existingUserByPhone = $this->userRepository->findOneBy(['phone' => $user->getPhone()]);
            if ($existingUserByPhone && $existingUserByPhone->getId() !== $user->getId()) { // Ensure it's not the same user on update
                return 'registration.phone.already_used';
            }
        }

        return null; // No uniqueness violations found
    }

    /**
     * Registers a new user.
     *
     * Generates a confirmation code, sets initial confirmation status and roles,
     * hashes the plain password, and persists the user entity to the database.
     *
     * @param User $user The User entity to register.
     * @param string $plainPassword The user's plain (unhashed) password.
     */
    public function registerUser(User $user, string $plainPassword): void
    {
        // Generate a unique confirmation code
        $confirmationCode = bin2hex(random_bytes(10)); // 20 characters long
        $user->setConfirmationCode($confirmationCode);
        $user->setIsConfirmed(false); // User is not confirmed until email verification
        $user->setRoles([Role::USER]); // Assign default user role

        // Hash the plain password before setting it on the user entity
        $user->setPassword(
            $this->hasher->hashPassword($user, $plainPassword)
        );

        // Save the new user to the database
        $this->userRepository->save($user);
        $this->logger->info(sprintf('User registered successfully: %s (ID: %d)', $user->getEmail(), $user->getId()));
    }

    /**
     * Sends a verification email to the registered user.
     *
     * Attempts to publish a verification email message to Kafka.
     *
     * @param User $user The User entity to send the email to.
     * @param string $confirmUrl The absolute URL for email confirmation.
     * @param string $locale The locale for the email content.
     * @return bool True if the email message was successfully published to Kafka, false otherwise.
     */
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
                'user_' . $user->getId(), // Message key for Kafka
            );
            
            $this->logger->info(sprintf('Verification email message published to Kafka for user ID: %d', $user->getId()));
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Kafka publishing failed for registration email: ' . $e->getMessage(), [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'exception' => $e,
            ]);
            
            return false;
        }
    }

    /**
     * Queues a verification email for later sending if direct sending fails.
     *
     * This method creates an `EmailQueue` entity and persists it, allowing for
     * a retry mechanism for email delivery.
     *
     * @param User $user The User entity for whom the email needs to be queued.
     * @param string $confirmUrl The absolute URL for email confirmation.
     * @param string $locale The locale for the email content.
     */
    public function queueVerificationEmail(User $user, string $confirmUrl, string $locale): void
    {
        $emailQueue = $this->emailFactory->createEmailQueue(
            EmailTemplate::VERIFICATION->value, // Use raw value of enum as string
            [
                'user' => $user->getId(), // Store only the user ID, not the full object
                'confirmUrl' => $confirmUrl,
            ],
            $locale,
        );
        
        $this->emailQueueRepository->save($emailQueue);
        $this->logger->info(sprintf('Verification email for user ID: %d queued for retry.', $user->getId()));
    }
}