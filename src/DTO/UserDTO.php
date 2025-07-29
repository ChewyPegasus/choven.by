<?php

declare(strict_types=1);

namespace App\DTO;

use App\DTO\DTO;

class UserDTO implements DTO
{
    public function __construct(
        public int $id,
        public string $email,
        public ?string $phone,
        public bool $isConfirmed,
        public array $roles,
    )
    {
    }

    public static function fromEntity(\App\Entity\User $user): self
    {
        return new self(
            $user->getId(),
            $user->getEmail(),
            $user->getPhoneString(),
            $user->isConfirmed(),
            $user->getRoles(),
        );
    }
}