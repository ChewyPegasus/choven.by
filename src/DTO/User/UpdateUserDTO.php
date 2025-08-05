<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\DTO\DTO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for updating existing users via API.
 */
class UpdateUserDTO implements DTO
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please enter a valid email address')]
    public ?string $email = null;

    #[Assert\Length(min: 6, minMessage: 'Password must be at least 6 characters long')]
    public ?string $password = null;

    public ?string $phone = null;

    public bool $isConfirmed = false;

    public bool $isAdmin = false;
}