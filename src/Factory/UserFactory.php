<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\User\CreateUserDTO;
use App\DTO\User\UpdateUserDTO;
use App\Entity\User;
use App\Enum\Role;
use App\Exception\UserNotFoundException;
use App\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Factory for creating and updating User entities.
 */
class UserFactory
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Creates a new User entity from CreateUserDTO.
     */
    public function createFromCreateDTO(CreateUserDTO $dto): array
    {
        $errors = $this->validator->validate($dto);
        
        if ($errors->count() > 0) {
            return [null, $errors];
        }

        // Check if user with email already exists
        try {
            $this->userRepository->findOneByEmail($dto->email);
            $errors->add(new \Symfony\Component\Validator\ConstraintViolation(
                'User with this email already exists',
                null,
                [],
                $dto,
                'email',
                $dto->email
            ));
            return [null, $errors];
        } catch (UserNotFoundException) {
            // User doesn't exist, continue
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->setPhone($dto->phone);
        $user->setIsConfirmed($dto->isConfirmed);

        $roles = [Role::USER];
        if ($dto->isAdmin) {
            $roles[] = Role::ADMIN;
        }
        $user->setRoles($roles);

        $entityErrors = $this->validator->validate($user);
        
        return [$user, $entityErrors];
    }

    /**
     * Updates an existing User entity from UpdateUserDTO.
     */
    public function updateFromUpdateDTO(User $user, UpdateUserDTO $dto): array
    {
        $errors = $this->validator->validate($dto);
        
        if ($errors->count() > 0) {
            return [$user, $errors];
        }

        // Check if email is being changed and if new email already exists
        if ($dto->email !== $user->getEmail()) {
            if ($this->userRepository->findOneBy(['email' => $dto->email])) {
                $errors->add(new \Symfony\Component\Validator\ConstraintViolation(
                    'User with this email already exists',
                    null,
                    [],
                    $dto,
                    'email',
                    $dto->email
                ));
                return [$user, $errors];
            }
            $user->setEmail($dto->email);
        }

        if ($dto->password !== null && !empty($dto->password)) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        }

        $user->setPhone($dto->phone);
        $user->setIsConfirmed($dto->isConfirmed);

        $roles = [Role::USER];
        if ($dto->isAdmin) {
            $roles[] = Role::ADMIN;
        }
        $user->setRoles($roles);

        $entityErrors = $this->validator->validate($user);
        
        return [$user, $entityErrors];
    }

    /**
     * Creates a basic User entity.
     */
    public function create(): User
    {
        return new User();
    }
}