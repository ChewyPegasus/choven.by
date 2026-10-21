<?php

declare(strict_types=1);

namespace App\Service\Retry;

use App\Enum\EmailTemplate;
use App\Repository\Interfaces\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for preparing context data for various email types.
 *
 * This service centralizes the logic for enriching or validating
 * context data required for different email templates before an Email DTO is created.
 */
class EmailContextBuilder
{
    /**
     * @var array<string, callable> Context handlers for different email types.
     */
    private array $contextHandlers = [];

    /**
     * Constructs a new EmailContextBuilder instance.
     *
     * @param UserRepositoryInterface $userRepository The repository for retrieving User entities.
     * @param LoggerInterface $logger The logger instance for recording operational logs.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger
    ) {
        $this->initContextHandlers();
    }

    /**
     * Initializes the context handlers mapping email types to their respective processing methods.
     *
     * This method sets up a dictionary where each key is an `EmailTemplate` enum value
     * and the corresponding value is a callable method that prepares the context
     * required for rendering and sending that specific email type.
     */
    private function initContextHandlers(): void
    {
        $this->contextHandlers = [
            EmailTemplate::VERIFICATION->value => [$this, 'processVerificationContext'],
            // Add other email types and their handlers here if needed
        ];
    }

    /**
     * Prepares the context data for an email based on its type.
     *
     * This method dynamically calls a registered context handler for the given
     * `emailType`. This allows for type-specific data enrichment (e.g., fetching
     * full `User` objects from IDs) before the DTO is created.
     *
     * @param EmailTemplate $emailType The EmailTemplate enum instance.
     * @param array<string, mixed> $context The initial context data for the email.
     * @return array<string, mixed>|null The prepared context, or `null` if the email should be skipped.
     * @throws \InvalidArgumentException If no context handler is registered for the given email type
     * and the email type requires specific context processing.
     */
    public function prepareContext(EmailTemplate $emailType, array $context): ?array
    {
        // Use specific context handler if available
        if (isset($this->contextHandlers[$emailType->value])) {
            $this->logger->debug(sprintf('Applying context handler for email type: %s', $emailType->name));
            return call_user_func($this->contextHandlers[$emailType->value], $context);
        }
        
        // By default, return the context unchanged if no specific handler is registered
        $this->logger->debug(sprintf('No specific context handler for email type: %s. Returning context unchanged.', $emailType->name));
        return $context;
    }

    /**
     * Context handler for verification emails.
     *
     * This method is responsible for retrieving the full `User` object
     * from the user ID present in the context. It also checks if the user
     * has already confirmed their account, in which case it signals to skip
     * sending the email.
     *
     * @param array<string, mixed> $context The initial context for the verification email. Expected to contain a 'user' ID.
     * @return array<string, mixed>|null The updated context with the `User` object, or `null` if the email should be skipped.
     * @throws \InvalidArgumentException If the 'user' ID is missing or the user is not found.
     */
    private function processVerificationContext(array $context): ?array
    {
        $userId = $context['user'] ?? null;

        if (!$userId) {
            $this->logger->error('Verification context is missing user ID.', ['context' => $context]);
            throw new \InvalidArgumentException('User ID is missing in verification email context.');
        }

        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            $this->logger->error(sprintf('User not found for verification email with ID: %s', $userId));
            throw new \InvalidArgumentException('User not found with ID: ' . $userId);
        }
        
        // If confirmation code is already null, the user has already confirmed their account.
        // In this case, there's no need to send the verification email.
        if ($user->getConfirmationCode() === null || $user->isConfirmed()) {
            $this->logger->info(sprintf('Skipping verification email for user ID %d as account is already confirmed.', $user->getId()));
            return null; // Signal to skip this email
        }
        
        // Update context with the full User object for DTO creation
        $context['user'] = $user;
        
        return $context;
    }
}