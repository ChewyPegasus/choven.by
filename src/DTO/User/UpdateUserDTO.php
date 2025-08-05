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
    #[Assert\NotBlank(message: 'user.form.error.email_required')]
    #[Assert\Email(message: 'user.form.error.email_invalid')]
    public ?string $email = null;

    #[Assert\Length(min: 6, minMessage: 'user.form.error.password_min_length')]
    public ?string $password = null;

    #[Assert\Length(max: 20, maxMessage: 'user.form.error.phone_max_length')]
    public ?string $phone = null;

    public bool $isConfirmed = false;

    public bool $isAdmin = false;
}