<?php

declare(strict_types=1);

namespace App\Service\Retry;

use App\Entity\EmailQueue;
use App\Enum\EmailTemplate;
use App\Factory\EmailFactory;
use App\Repository\Interfaces\EmailQueueRepositoryInterface;
use App\Repository\Interfaces\FailedEmailRepositoryInterface;
use App\Service\Retry\EmailContextBuilder;
use App\Service\Retry\EmailSendingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Service for managing the retry mechanism of failed email deliveries.
 *
 * This service orchestrates the process of identifying, retrying, and
 * archiving emails that have failed initial delivery. It delegates
 * email context preparation and actual email sending to dedicated services,
 * focusing primarily on the retry logic and queue state management.
 */
class EmailRetryService
{
    /**
     * @var int The maximum number of attempts to send an email before it's considered permanently failed.
     */
    private const MAX_ATTEMPTS = 2;

    /**
     * Constructs a new EmailRetryService instance.
     *
     * @param EmailQueueRepositoryInterface $emailQueueRepo The repository for managing email queue entries.
     * @param FailedEmailRepositoryInterface $failedEmailRepo The repository for managing permanently failed email entries.
     * @param EmailSendingService $emailSendingService The service responsible for sending emails (via Kafka or directly).
     * @param EmailFactory $emailFactory The factory for creating email-related DTOs and entities like `FailedEmail`.
     * @param LocaleSwitcher $localeSwitcher The service for managing the application's locale for email rendering.
     * @param LoggerInterface $logger The logger instance for recording operational logs and errors.
     * @param EmailContextBuilder $emailContextBuilder The service responsible for preparing email context data.
     */
    public function __construct(
        private readonly EmailQueueRepositoryInterface $emailQueueRepo,
        private readonly FailedEmailRepositoryInterface $failedEmailRepo,
        private readonly EmailSendingService $emailSendingService,
        private readonly EmailFactory $emailFactory,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
        private readonly EmailContextBuilder $emailContextBuilder,
    ) {
    }

    /**
     * Main method to process the email queue.
     *
     * This method orchestrates the queue processing by first identifying and moving
     * emails that have exhausted all retry attempts to the `failed_emails` table,
     * and then attempting to re-send emails that still have retry attempts left.
     * It logs the start and end of the processing cycle.
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
                $this->handlePermanentEmailFailure($email, 'Maximum retry attempts reached');
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
                $this->attemptSendQueuedEmail($email);
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Unexpected error during processing of email ID %d: %s',
                    $email->getId(),
                    $e->getMessage()
                ), ['exception' => $e, 'email_id' => $email->getId()]);
                
                // Ensure we handle the email failure even if attemptSendQueuedEmail throws an unexpected exception
                $this->handleTemporaryEmailFailure($email, 'Unexpected processing error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Attempts to send a single queued email.
     *
     * This method sets the correct locale, increments the attempt count,
     * prepares the email context (e.g., fetching related entities via `EmailContextBuilder`),
     * and then attempts to send the email using `EmailSendingService`.
     * It handles the outcome by either removing the email from the queue
     * or marking it for future retries/permanent failure.
     *
     * @param EmailQueue $queuedEmail The email queue entity to process.
     * @return void
     */
    private function attemptSendQueuedEmail(EmailQueue $queuedEmail): void
    {
        $this->setLocale($queuedEmail->getLocale());
        
        // Increment attempts before trying to send
        $queuedEmail->incrementAttempts();
        $this->emailQueueRepo->save($queuedEmail); // Persist attempt count
        
        try {
            $emailType = EmailTemplate::from($queuedEmail->getEmailType());
            $context = $this->emailContextBuilder->prepareContext($emailType, $queuedEmail->getContext());
            
            // If context handler returned null, it means the email should be skipped (e.g., user already verified).
            if ($context === null) {
                $this->removeFromQueue($queuedEmail, sprintf('Email ID %d skipped as context processing indicated no action needed.', $queuedEmail->getId()));
                return;
            }
            
            $emailDto = $this->emailFactory->createDTO($emailType, $context);
            
            $sendSuccess = $this->emailSendingService->send($emailType, $emailDto, $queuedEmail->getId());
            
            if ($sendSuccess) {
                $this->removeFromQueue(
                    $queuedEmail, 
                    sprintf('Email ID %d (type: %s) successfully sent.', $queuedEmail->getId(), $emailType->name)
                );
            } elseif ($queuedEmail->getAttempts() >= self::MAX_ATTEMPTS) {
                // If sending failed and max attempts reached, move to failed_emails
                $this->handlePermanentEmailFailure($queuedEmail, 'All configured sending methods failed after retries.');
            } else {
                // If sending failed but attempts < MAX_ATTEMPTS, it remains in queue for next cycle.
                // A warning is logged by EmailSendingService if it fails.
                $this->logger->warning(sprintf(
                    'Email ID %d (type: %s) failed attempt %d/%d. Will retry later.',
                    $queuedEmail->getId(),
                    $emailType->name,
                    $queuedEmail->getAttempts(),
                    self::MAX_ATTEMPTS
                ));
            }
            
        } catch (\Exception $e) {
            // Handle any exception that occurred during the preparation or initial sending attempt
            $this->logger->error(sprintf(
                'Error processing queued email ID %d (type: %s): %s',
                $queuedEmail->getId(),
                $queuedEmail->getEmailType(),
                $e->getMessage()
            ), ['exception' => $e, 'email_id' => $queuedEmail->getId()]);

            // Mark as failed attempt, will be retried if attempts < MAX_ATTEMPTS
            $this->handleTemporaryEmailFailure($queuedEmail, 'Processing failed due to exception: ' . $e->getMessage());
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
     * Handles an email that has permanently failed to send after reaching maximum attempts.
     *
     * This method creates a `FailedEmail` entry and moves the email from the `email_queue`
     * to the `failed_emails` table.
     *
     * @param EmailQueue $queuedEmail The email queue entity that permanently failed.
     * @param string $error A description of the error that caused the permanent failure.
     * @return void
     */
    private function handlePermanentEmailFailure(
        EmailQueue $queuedEmail, 
        string $error,
    ): void {
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
        
        $this->failedEmailRepo->flush(); // Flush immediately to ensure changes are persisted
        $this->emailQueueRepo->flush(); // Flush immediately to ensure changes are persisted
    }

    /**
     * Handles an email that has temporarily failed to send but still has retry attempts remaining.
     *
     * This method logs a warning, indicating that the email will be retried in a subsequent run.
     * The email remains in the queue.
     *
     * @param EmailQueue $queuedEmail The email queue entity that temporarily failed.
     * @param string $error A description of the error that caused the temporary failure.
     * @return void
     */
    private function handleTemporaryEmailFailure(
        EmailQueue $queuedEmail, 
        string $error,
    ): void {
        $this->logger->warning(sprintf(
            'Email ID %d (type: %s) failed attempt %d/%d. Will retry. Error: %s',
            $queuedEmail->getId(),
            $queuedEmail->getEmailType(),
            $queuedEmail->getAttempts(),
            self::MAX_ATTEMPTS,
            $error
        ));
        
        // No flush needed here as the attempt count was already saved at the start of attemptSendQueuedEmail,
        // and the email remains in the queue.
    }
}