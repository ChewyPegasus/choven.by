<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\DTO\DTO;
use App\Entity\User;

/**
 * Data Transfer Object (DTO) for User entities.
 *
 * This DTO is used to transfer user data between different layers of the application,
 * particularly for API responses or form handling, without exposing the full
 * `User` entity. It provides a concise and immutable representation of user information.
 */
class UserDTO implements DTO
{
    /**
     * Constructs a new UserDTO instance.
     *
     * @param int $id The unique identifier of the user.
     * @param string $email The email address of the user.
     * @param string|null $phone The phone number of the user, or null if not set.
     * @param bool $isConfirmed The confirmation status of the user's account.
     * @param array $roles An array of roles assigned to the user.
     */
    public function __construct(
        public int $id,
        public string $email,
        public ?string $phone,
        public bool $isConfirmed,
        public array $roles,
    ) {
    }

    /**
     * Creates a UserDTO instance from a User entity.
     *
     * This static factory method facilitates converting a `User` entity
     * into a `UserDTO`, extracting only the necessary public properties.
     *
     * @param \App\Entity\User $user The User entity to convert.
     * @return self A new UserDTO instance.
     */
    public static function fromEntity(User $user): self
    {
        return new self(
            $user->getId(),
            $user->getEmail(),
            $user->getPhoneString(), // Assuming getPhoneString() returns string or null
            $user->isConfirmed(),
            $user->getRoles(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'isConfirmed' => $this->isConfirmed,
            'roles' => $this->roles,
        ];
    }
}