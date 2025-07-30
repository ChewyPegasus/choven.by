<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailQueue;
use App\Enum\EmailTemplate;
use App\Factory\EmailFactory;
use App\Repository\Interfaces\EmailQueueRepositoryInterface;
use App\Repository\Interfaces\FailedEmailRepositoryInterface;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Service\Messaging\Producer\Producer;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Service for retrying failed email deliveries.
 *
 * This service manages the email queue, attempting to re-send emails that
 * previously failed. It supports different sending mechanisms (Kafka and direct sending),
 * handles context preparation for various email types, and moves permanently
 * failed emails to a dedicated `failed_emails` table.
 */
class EmailRetryService
{
    /**
     * @var int The maximum number of attempts to send an email before it's considered permanently failed.
     */
    private const MAX_ATTEMPTS = 2;

    /**
     * @var array<string, callable> Context handlers for different email types.
     * The key is the email type (string value of `EmailTemplate` enum), and the value
     * is a callable that prepares the context for that specific email type.
     */
    private array $contextHandlers = [];

    /**
     * Constructs a new EmailRetryService instance.
     *
     * @param EmailQueueRepositoryInterface $emailQueueRepo The repository for managing email queue entries.
     * @param FailedEmailRepositoryInterface $failedEmailRepo The repository for managing permanently failed email entries.
     * @param Producer $kafkaProducer The Kafka producer service for sending messages to Kafka topics.
     * @param EmailSender $emailSender The service for direct email sending via Symfony Mailer.
     * @param EmailFactory $emailFactory The factory for creating email-related DTOs and entities like `FailedEmail`.
     * @param LocaleSwitcher $localeSwitcher The service for managing the application's locale for email rendering.
     * @param LoggerInterface $logger The logger instance for recording operational logs and errors.
     * @param UserRepositoryInterface $userRepository The repository for retrieving User entities, used in context handlers.
     * @param array<string, string> $topicMap An associative array mapping email types (string values of `EmailTemplate`) to Kafka topic names.
     */
    public function __construct(
        private readonly EmailQueueRepositoryInterface $emailQueueRepo,
        private readonly FailedEmailRepositoryInterface $failedEmailRepo,
        private readonly Producer $kafkaProducer,
        private readonly EmailSender $emailSender,
        private readonly EmailFactory $emailFactory,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
        private readonly UserRepositoryInterface $userRepository,
        private array $topicMap,
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
        // Register context handlers for different email types
        $this->contextHandlers = [
            EmailTemplate::VERIFICATION->value => [$this, 'processVerificationContext'],
            // Add other email types and their handlers here if needed
        ];
    }

    /**
     * Main method to process the email queue.
     *
     * This method orchestrates the queue processing by first identifying and moving
     * emails that have exhausted all retry attempts to the `failed_emails` table,
     * and then attempting to re-send emails that still have retry attempts left.
     * It logs the start of the processing cycle.
     *
     * @return void
     */
    public function processQueue(): void
    {
        $this->logger->info('Starting email queue processing');

        // First, find emails that have reached max attempts and move them to failed_emails
        $this->processFinallyFailedEmails();
        
        // Then process emails that still have retry attempts left
        $this->processRetryableEmails();

        $this->logger->info('Finished email queue processing');
    }
    
    /**
     * Processes emails that have reached the maximum number of retry attempts.
     *
     * These emails are considered permanently failed and are moved from the `email_queue`
     * table to the `failed_emails` table.
     *
     * @return void
     */
    private function processFinallyFailedEmails(): void
    {
        $failedEmails = $this->emailQueueRepo->findFailedEmails(self::MAX_ATTEMPTS);
        
        if (empty($failedEmails)) {
            $this->logger->debug('No permanently failed emails found to move.');
            return;
        }
        
        $this->logger->info(sprintf('Found %d permanently failed emails to move to failed_emails table.', count($failedEmails)));
        
        foreach ($failedEmails as $email) {
            try {
                $this->handleFailedEmail($email, 'Maximum retry attempts reached');
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Error moving permanently failed email ID %d to failed_emails: %s',
                    $email->getId(),
                    $e->getMessage()
                ), ['exception' => $e, 'email_id' => $email->getId()]);
            }
        }
    }
    
    /**
     * Processes emails that still have retry attempts remaining.
     *
     * This method fetches emails from the queue that are eligible for retry
     * and attempts to process each one. Any unexpected errors during processing
     * are caught and the email is handled as a failed attempt.
     *
     * @return void
     */
    private function processRetryableEmails(): void
    {
        // Find emails that have attempts less than MAX_ATTEMPTS
        $emailsToRetry = $this->emailQueueRepo->findEmailsToRetry(self::MAX_ATTEMPTS - 1);

        if (empty($emailsToRetry)) {
            $this->logger->info('No emails found to retry at this time.');
            
            return;
        }

        $this->logger->info(sprintf('Found %d emails to retry.', count($emailsToRetry)));

        foreach ($emailsToRetry as $email) {
            try {
                $this->processQueuedEmail($email);
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Unexpected error during processing of email ID %d: %s',
                    $email->getId(),
                    $e->getMessage()
                ), ['exception' => $e, 'email_id' => $email->getId()]);
                
                // Ensure we handle the email failure even if processQueuedEmail throws an unexpected exception
                $this->handleFailedEmail($email, 'Unexpected processing error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Processes a single queued email for sending.
     *
     * This involves setting the correct locale, incrementing the attempt count,
     * preparing the email context (e.g., fetching related entities),
     * and then attempting to send the email, first via Kafka, then directly.
     * If all attempts fail and `MAX_ATTEMPTS` is reached, the email is marked as failed.
     *
     * @param EmailQueue $queuedEmail The email queue entity to process.
     * @return void
     */
    private function processQueuedEmail(EmailQueue $queuedEmail): void
    {
        $this->setLocale($queuedEmail->getLocale());
        
        // Increment attempts before trying to send
        $queuedEmail->incrementAttempts();
        $this->emailQueueRepo->save($queuedEmail); // Persist attempt count
        
        try {
            $emailType = $queuedEmail->getEmailType();
            $context = $this->prepareContext($emailType, $queuedEmail->getContext());
            
            // If context handler returned null, it means the email should be skipped (e.g., user already verified).
            if ($context === null) {
                $this->removeFromQueue($queuedEmail, sprintf('Email ID %d skipped as context processing indicated no action needed.', $queuedEmail->getId()));
                return;
            }
            
            $emailTypeEnum = EmailTemplate::from($emailType);
            $emailDto = $this->emailFactory->createDTO($emailTypeEnum, $context);
            
            // Try sending methods in sequence: Kafka first, then direct sending as a fallback
            $sendSuccess = $this->sendViaKafka($queuedEmail, $emailType, $emailDto) || 
                           $this->sendDirectly($queuedEmail, $emailTypeEnum, $emailDto);
            
            // If both sending methods failed and the email has now reached max attempts
            if (!$sendSuccess && $queuedEmail->getAttempts() >= self::MAX_ATTEMPTS) {
                $this->handleFailedEmail($queuedEmail, 'All configured sending methods failed after retries.');
            }
            // If sendSuccess is false but attempts < MAX_ATTEMPTS, the email remains in queue for next cycle
            
        } catch (\Exception $e) {
            // Handle any exception that occurred during the preparation or initial sending attempt
            $this->logger->error(sprintf(
                'Error processing queued email ID %d (type: %s): %s',
                $queuedEmail->getId(),
                $queuedEmail->getEmailType(),
                $e->getMessage()
            ), ['exception' => $e, 'email_id' => $queuedEmail->getId()]);

            // Mark as failed attempt
            $this->handleFailedEmail($queuedEmail, 'Processing failed due to exception: ' . $e->getMessage());
        } finally {
            $this->localeSwitcher->reset(); // Always reset locale after processing a single email
        }
    }
    
    /**
     * Sets the application locale for the current operation.
     *
     * If a locale is provided, it is set using the `LocaleSwitcher`. If no locale
     * is provided (null), the locale is reset to the default.
     *
     * @param string|null $locale The locale string (e.g., 'en', 'fr') or null to reset.
     * @return void
     */
    private function setLocale(?string $locale): void
    {
        if ($locale) {
            $this->localeSwitcher->setLocale($locale);
            $this->logger->debug(sprintf('Locale set to "%s".', $locale));
        } else {
            $this->localeSwitcher->reset();
            $this->logger->debug('Locale reset to default.');
        }
    }
    
    /**
     * Prepares the context data for an email based on its type.
     *
     * This method dynamically calls a registered context handler for the given
     * `emailType`. This allows for type-specific data enrichment (e.g., fetching
     * full `User` objects from IDs) before the DTO is created.
     *
     * @param string $emailType The string value of the `EmailTemplate` enum (e.g., 'verification').
     * @param array<string, mixed> $context The initial context data for the email.
     * @return array<string, mixed>|null The prepared context, or `null` if the email should be skipped.
     * @throws \InvalidArgumentException If no context handler is registered for the given email type.
     */
    private function prepareContext(string $emailType, array $context): ?array
    {
        // Use specific context handler if available
        if (isset($this->contextHandlers[$emailType])) {
            $this->logger->debug(sprintf('Applying context handler for email type: %s', $emailType));
            return call_user_func($this->contextHandlers[$emailType], $context);
        }
        
        // By default, return the context unchanged if no specific handler is registered
        $this->logger->debug(sprintf('No specific context handler for email type: %s. Returning context unchanged.', $emailType));
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
    
    /**
     * Attempts to send an email message via Kafka.
     *
     * This is the preferred method of sending. If successful, the email is removed from the queue.
     *
     * @param EmailQueue $queuedEmail The email queue entity being processed.
     * @param string $emailType The string value of the email template type.
     * @param object $emailDto The DTO representing the email content to be sent. (Type hint changed to object as AbstractEmailDTO is not imported directly in this method)
     * @return bool True if the email was successfully published to Kafka and removed from the queue, false otherwise.
     */
    private function sendViaKafka(EmailQueue $queuedEmail, string $emailType, object $emailDto): bool
    {
        try {
            // Determine the appropriate Kafka topic for this email type
            $topic = $this->determineKafkaTopic($emailType);
            
            // Send the email DTO to Kafka
            $this->kafkaProducer->produce(
                $topic,
                $emailDto, // Assuming produce method accepts AbstractEmailDTO or compatible
                'retry_' . $queuedEmail->getId() // Use a unique key for the message
            );
            
            // If Kafka publishing is successful, remove the email from the queue
            $this->removeFromQueue(
                $queuedEmail, 
                sprintf('Email ID %d (type: %s) successfully published to Kafka topic "%s".', $queuedEmail->getId(), $emailType, $topic)
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Kafka delivery failed for email ID %d (type: %s) after %d attempts: %s',
                $queuedEmail->getId(),
                $emailType,
                $queuedEmail->getAttempts(),
                $e->getMessage()
            ), ['exception' => $e, 'email_id' => $queuedEmail->getId(), 'email_type' => $emailType]);
            
            return false;
        }
    }
    
    /**
     * Attempts to send an email directly using the `EmailSender` service.
     *
     * This serves as a fallback mechanism if sending via Kafka fails or is not desired.
     * If successful, the email is removed from the queue.
     *
     * @param EmailQueue $queuedEmail The email queue entity being processed.
     * @param EmailTemplate $emailType The `EmailTemplate` enum instance.
     * @param object $emailDto The DTO representing the email content to be sent. (Type hint changed to object as AbstractEmailDTO is not imported directly in this method)
     * @return bool True if the email was sent directly and removed from the queue, false otherwise.
     */
    private function sendDirectly(EmailQueue $queuedEmail, EmailTemplate $emailType, object $emailDto): bool
    {
        try {
            // Send the email directly using the EmailSender service
            $this->emailSender->send($emailDto); // Assuming send method accepts AbstractEmailDTO or compatible
            
            // If direct sending is successful, remove the email from the queue
            $this->removeFromQueue(
                $queuedEmail,
                sprintf('Email ID %d (type: %s) successfully sent directly.', $queuedEmail->getId(), $emailType->name)
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Direct email sending failed for email ID %d (type: %s) after %d attempts: %s',
                $queuedEmail->getId(),
                $emailType->name,
                $queuedEmail->getAttempts(),
                $e->getMessage()
            ), ['exception' => $e, 'email_id' => $queuedEmail->getId(), 'email_type' => $emailType->name]);
            
            return false;
        }
    }
    
    /**
     * Removes an email entry from the email queue.
     *
     * This method is called upon successful delivery (either via Kafka or directly)
     * or when an email is permanently failed and moved to the `failed_emails` table.
     *
     * @param EmailQueue $queuedEmail The email queue entity to remove.
     * @param string $logMessage The message to log upon removal.
     * @return void
     */
    private function removeFromQueue(EmailQueue $queuedEmail, string $logMessage): void
    {
        $this->emailQueueRepo->remove($queuedEmail);
        $this->emailQueueRepo->flush(); // Flush immediately to remove from DB
        $this->logger->info($logMessage);
    }

    /**
     * Handles an email that has failed to send.
     *
     * If the email has reached the `MAX_ATTEMPTS`, it is moved from the `email_queue`
     * to the `failed_emails` table. Otherwise, a warning is logged, indicating
     * that the email will be retried in a subsequent run.
     *
     * @param EmailQueue $queuedEmail The email queue entity that failed.
     * @param string $error A description of the error that caused the failure.
     * @return void
     */
    private function handleFailedEmail(
        EmailQueue $queuedEmail, 
        string $error,
    ): void {
        // If max attempts reached, create a FailedEmail entry and remove from queue
        if ($queuedEmail->getAttempts() >= self::MAX_ATTEMPTS) {
            $failedEmail = $this->emailFactory->createFailedEmail(
                $queuedEmail->getEmailType(),
                $queuedEmail->getContext(),
                $error,
                $queuedEmail->getAttempts(),
                $queuedEmail->getCreatedAt(),
            );
            
            // Copy optional fields from the queued email to the failed email
            if ($queuedEmail->getLastAttemptAt()) {
                $failedEmail->setLastAttemptAt($queuedEmail->getLastAttemptAt());
            }
            
            if ($queuedEmail->getLocale()) {
                $failedEmail->setLocale($queuedEmail->getLocale());
            }
            
            // Persist the failed email and remove the original from the queue
            $this->failedEmailRepo->save($failedEmail);
            $this->emailQueueRepo->remove($queuedEmail);
            
            $this->logger->error(sprintf(
                'Email ID %d (type: %s) moved to failed_emails after %d attempts. Error: %s',
                $queuedEmail->getId(),
                $queuedEmail->getEmailType(),
                $queuedEmail->getAttempts(),
                $error
            ));
        } else {
            // If still within retry attempts, just log a warning
            $this->logger->warning(sprintf(
                'Email ID %d (type: %s) failed attempt %d/%d. Will retry. Error: %s',
                $queuedEmail->getId(),
                $queuedEmail->getEmailType(),
                $queuedEmail->getAttempts(),
                self::MAX_ATTEMPTS,
                $error
            ));
        }
        
        // Flush all pending changes to the database
        $this->failedEmailRepo->flush();
        $this->emailQueueRepo->flush();
    }
    
    /**
     * Determines the appropriate Kafka topic name for a given email type.
     *
     * This method looks up the `emailType` in the `$topicMap` injected into the service.
     *
     * @param string $emailType The string value of the email template type (e.g., 'verification').
     * @return string The Kafka topic name.
     * @throws \InvalidArgumentException If no topic is mapped for the given email type.
     */
    private function determineKafkaTopic(string $emailType): string
    {
        if (!isset($this->topicMap[$emailType])) {
            $this->logger->error(sprintf('No Kafka topic configured for email type: %s', $emailType));
            throw new \InvalidArgumentException('Unknown email type or no Kafka topic configured for: ' . $emailType);
        }
        
        return $this->topicMap[$emailType];
    }
}