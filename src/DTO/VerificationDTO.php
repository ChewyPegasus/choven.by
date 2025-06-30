<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;
use App\Enum\EmailTemplate;

readonly class VerificationDTO extends AbstractEmailDTO
{
    private string $email;
    private int $id;
    private string $confirmationCode;
    private string $locale;

    public function __construct(
        User $user,
        private string $confirmUrl,
        ?string $locale = null,
    )
    {
        $this->id = $user->getId();
        $this->email = $user->getEmail();
        $this->confirmationCode = $user->getConfirmationCode();
        $this->locale = $locale ?? 'ru';
    }

    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getConfirmationCode(): string
    {
        return $this->confirmationCode;
    }

    public function getEmailTemplate(): EmailTemplate
    {
        return EmailTemplate::VERIFICATION;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getContext(): array
    {
        return [
            'confirmUrl' => $this->confirmUrl,
            'email' => $this->email,
            'id' => $this->id,
            'locale' => $this->locale,
        ];
    }
}
