<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;
use App\Enum\EmailTemplate;

readonly class VerificationDTO extends AbstractEmailDTO
{
    public function __construct(
        private User $user,
        private string $confirmUrl,
    )
    {
    }

    public function getEntity(): object
    {
        return $this->user;
    }

    public function getConfirmUrl(): ?string
    {
        return $this->confirmUrl;
    }

    public function getEmailTemplate(): EmailTemplate
    {
        return EmailTemplate::VERIFICATION;
    }
    
    public function getAdditionalContext(): array
    {
        return [
            'confirmUrl' => $this->confirmUrl,
        ];
    }
}
