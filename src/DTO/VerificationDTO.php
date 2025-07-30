<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;
use App\Enum\EmailTemplate;

/**
 * Data Transfer Object (DTO) for email verification purposes, extending AbstractEmailDTO.
 *
 * This DTO encapsulates the necessary information to send an email verification link
 * to a user, including the user's details, the generated confirmation URL, and locale.
 * It ensures that all required data for the verification email is readily available.
 */
readonly class VerificationDTO extends AbstractEmailDTO
{
    private string $email;
    private int $id;
    private string $confirmationCode;
    private string $locale;

    /**
     * Constructs a new VerificationDTO instance.
     *
     * Initializes the DTO with user information, the confirmation URL, and an optional locale.
     * If no locale is provided, it defaults to 'ru'.
     *
     * @param User $user The User entity for whom the verification email is being sent.
     * @param string $confirmUrl The absolute URL that the user will click to confirm their email.
     * @param string|null $locale The locale for the email content (e.g., 'en', 'ru', 'be'). Defaults to 'ru'.
     */
    public function __construct(
        User $user,
        private string $confirmUrl,
        ?string $locale = null,
    ) {
        $this->id = $user->getId();
        $this->email = $user->getEmail();
        $this->confirmationCode = $user->getConfirmationCode();
        $this->locale = $locale ?? 'ru';
    }

    /**
     * Retrieves the confirmation URL.
     *
     * This URL is typically sent in the verification email to allow the user
     * to confirm their email address.
     *
     * @return string The absolute URL for email confirmation.
     */
    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }
    
    /**
     * Retrieves the recipient's email address.
     *
     * This method overrides the abstract method from `AbstractEmailDTO` to
     * provide the specific email address for this verification DTO.
     *
     * @return string The user's email address.
     */
    public function getEmail(): string
    {
        return $this->email;
    }
    
    /**
     * Retrieves the user's ID.
     *
     * @return int The ID of the user.
     */
    public function getId(): int
    {
        return $this->id;
    }
    
    /**
     * Retrieves the unique confirmation code for the user.
     *
     * This code is part of the confirmation URL and is used to identify
     * the user for verification.
     *
     * @return string The confirmation code.
     */
    public function getConfirmationCode(): string
    {
        return $this->confirmationCode;
    }

    /**
     * Specifies the email template to be used for this DTO.
     *
     * For `VerificationDTO`, this is always `EmailTemplate::VERIFICATION`.
     * This method overrides the abstract method from `AbstractEmailDTO`.
     *
     * @return EmailTemplate The email template for verification.
     */
    public function getEmailTemplate(): EmailTemplate
    {
        return EmailTemplate::VERIFICATION;
    }

    /**
     * Retrieves the locale set for this verification DTO.
     *
     * This locale can be used to render the email content in the appropriate language.
     *
     * @return string The locale (e.g., 'en', 'ru').
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Provides the context data needed for rendering the email verification template.
     *
     * Returns an associative array containing all relevant data that can be used
     * by a Twig template to generate the email content.
     * This method overrides the abstract method from `AbstractEmailDTO`.
     *
     * @return array An associative array of context data for the email template.
     */
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