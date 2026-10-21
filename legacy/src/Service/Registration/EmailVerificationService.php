<?php

declare(strict_types=1);

namespace App\Service\Registration;

use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Repository\Interfaces\UserRepositoryInterface;

/**
 * Service for handling email verification during user registration.
 *
 * This service provides methods to find a user by their confirmation code
 * and to mark a user's email as confirmed.
 */
class EmailVerificationService
{
    /**
     * Constructs a new EmailVerificationService instance.
     *
     * @param UserRepositoryInterface $userRepository The repository for managing User entities.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Finds a user by their email confirmation code.
     *
     * @param string $code The unique confirmation code.
     * @return User|null The User entity if found, or null if no user matches the code.
     */
    public function findUserByConfirmationCode(string $code): ?User
    {
        try {
            $user = $this->userRepository->findOneByConfirmationCode($code);
        } catch (UserNotFoundException $e) {
            return null;
        }

        return $user;
    }

    /**
     * Confirms a user's email address.
     *
     * Sets the `isConfirmed` property of the user to true and clears the `confirmationCode`.
     * The changes are then persisted to the database.
     *
     * @param User $user The User entity to be confirmed.
     */
    public function confirmUser(User $user): void
    {
        $user->setIsConfirmed(true);
        $user->setConfirmationCode(null); // Clear the confirmation code after successful verification

        // Only flush the changes to the database. The user object should already be managed by the EntityManager
        // or will be implicitly persisted if `save` method on repository is used which does persist and flush.
        // Assuming the `confirmUser` method is called within a unit of work where the user object is already managed.
        $this->userRepository->flush(); 
    }
}