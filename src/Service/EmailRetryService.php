<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailQueue;
use App\Entity\FailedEmail;
use App\Entity\User;
use App\Enum\EmailTemplate;
use App\Factory\EmailFactory;
use App\Repository\EmailQueueRepository;
use App\Repository\FailedEmailRepository;
use App\Repository\UserRepository;
use App\Service\Messaging\Producer\Producer;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\LocaleSwitcher;

class EmailRetryService
{
    private const MAX_ATTEMPTS = 2;
    
    /**
     * Map of email types to Kafka topics
     */
    private array $topicMap = [];

    /**
     * Context handlers for different email types
     */
    private array $contextHandlers = [];

    public function __construct(
        private readonly EmailQueueRepository $emailQueueRepo,
        private readonly FailedEmailRepository $failedEmailRepo,
        private readonly Producer $kafkaProducer,
        private readonly EmailSender $emailSender,
        private readonly EmailFactory $emailFactory,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
        private readonly string $orderTopic,
        private readonly string $registrationTopic,
        private readonly UserRepository $userRepository,
    )
    {
        $this->initTopicMap();
        $this->initContextHandlers();
    }

    private function initTopicMap(): void
    {
        $this->topicMap = [
            EmailTemplate::ORDER_CONFIRMATION->value => $this->orderTopic,
            EmailTemplate::VERIFICATION->value => $this->registrationTopic,
        ];
    }

    private function initContextHandlers(): void
    {
        // Register context handlers for different email types
        $this->contextHandlers = [
            EmailTemplate::VERIFICATION->value => [$this, 'processVerificationContext'],
        ];
    }

    /**
     * Main method to process email queue
     * 1. Process regular emails (attempts < MAX_ATTEMPTS)
     * 2. Move failed emails (attempts >= MAX_ATTEMPTS) to failed_emails table
     */
    public function processQueue(): void
    {
        $this->logger->info('Starting email queue processing');

        // First, find emails that have reached max attempts and move them to failed_emails
        $this->processFinallyFailedEmails();
        
        // Then process emails that still have retry attempts left
        $this->processRetryableEmails();
    }
    
    /**
     * Process emails that have reached max attempts
     */
    private function processFinallyFailedEmails(): void
    {
        $failedEmails = $this->emailQueueRepo->findFailedEmails(self::MAX_ATTEMPTS);
        
        if (empty($failedEmails)) {
            return;
        }
        
        $this->logger->info(sprintf('Found %d permanently failed emails to move', count($failedEmails)));
        
        foreach ($failedEmails as $email) {
            try {
                $this->handleFailedEmail($email, 'Maximum retry attempts reached');
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Error processing failed email ID %d: %s',
                    $email->getId(),
                    $e->getMessage()
                ));
            }
        }
    }
    
    /**
     * Process emails that still have retry attempts left
     */
    private function processRetryableEmails(): void
    {
        $emailsToRetry = $this->emailQueueRepo->findEmailsToRetry(self::MAX_ATTEMPTS - 1);

        if (empty($emailsToRetry)) {
            $this->logger->info('No emails to retry');
            
            return;
        }

        $this->logger->info(sprintf('Found %d emails to retry', count($emailsToRetry)));

        foreach ($emailsToRetry as $email) {
            try {
                $this->processQueuedEmail($email);
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Unexpected error processing email ID %d: %s',
                    $email->getId(),
                    $e->getMessage()
                ));
                
                // Ensure we handle the email failure even if processQueuedEmail throws
                $this->handleFailedEmail($email, 'Processing error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Process a single queued email
     * - Set locale
     * - Increment attempts
     * - Prepare context
     * - Try to send via Kafka first
     * - If Kafka fails, try direct sending
     * - If both fail, the email stays in queue (until MAX_ATTEMPTS)
     */
    private function processQueuedEmail(EmailQueue $queuedEmail): void
    {
        $this->setLocale($queuedEmail->getLocale());
        
        $queuedEmail->incrementAttempts();
        $this->emailQueueRepo->save($queuedEmail);
        
        try {
            $emailType = $queuedEmail->getEmailType();
            $context = $this->prepareContext($emailType, $queuedEmail->getContext());
            
            // Skip processing if context handler returned null (e.g., user already verified)
            if ($context === null) {
                $this->removeFromQueue($queuedEmail, 'Email skipped: context processing indicated no action needed');
                
                return;
            }
            
            $emailTypeEnum = EmailTemplate::from($emailType);
            $emailDto = $this->emailFactory->createDTO($emailTypeEnum, $context);
            
            // Try sending methods in sequence
            $sendSuccess = $this->sendViaKafka($queuedEmail, $emailType, $emailDto) || 
                           $this->sendDirectly($queuedEmail, $emailTypeEnum, $emailDto);
            
            if (!$sendSuccess && $queuedEmail->getAttempts() >= self::MAX_ATTEMPTS) {
                // If both methods failed and we've reached max attempts, move to failed_emails
                $this->handleFailedEmail($queuedEmail, 'All sending methods failed');
            }
            
        } catch (\Exception $e) {
            // Handle any exception during processing
            $this->handleFailedEmail($queuedEmail, 'Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Set the locale for email sending
     */
    private function setLocale(?string $locale): void
    {
        if ($locale) {
            $this->localeSwitcher->setLocale($locale);
        } else {
            $this->localeSwitcher->reset();
        }
    }
    
    /**
     * Prepare context for email, applying type-specific transformations
     */
    private function prepareContext(string $emailType, array $context): ?array
    {
        // Use specific context handler if available
        if (isset($this->contextHandlers[$emailType])) {
            return call_user_func($this->contextHandlers[$emailType], $context);
        }
        
        // By default, return the context unchanged
        return $context;
    }
    
    /**
     * Context handler for verification emails
     */
    private function processVerificationContext(array $context): ?array
    {
        $userId = $context['user'];
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            throw new \InvalidArgumentException('User not found with ID: ' . $userId);
        }
        
        // If code is already null, user has already confirmed their account
        if ($user->getConfirmationCode() === null) {
            return null; // Signal to skip this email
        }
        
        // Update context with full user object for DTO creation
        $context['user'] = $user;
        
        return $context;
    }
    
    /**
     * Try to send email via Kafka
     */
    private function sendViaKafka(EmailQueue $queuedEmail, string $emailType, $emailDto): bool
    {
        try {
            // Determine appropriate Kafka topic for this email type
            $topic = $this->determineKafkaTopic($emailType);
            
            // Send to Kafka
            $this->kafkaProducer->produce(
                $topic,
                $emailDto,
                'retry_' . $queuedEmail->getId()
            );
            
            // If successful, remove from queue
            $this->removeFromQueue(
                $queuedEmail, 
                sprintf('Email sent via Kafka (type: %s, id: %d)', $emailType, $queuedEmail->getId())
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Kafka delivery failed for email id %d: %s',
                $queuedEmail->getId(),
                $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Try to send email directly via EmailSender
     */
    private function sendDirectly(EmailQueue $queuedEmail, EmailTemplate $emailType, $emailDto): bool
    {
        try {
            // Send email directly
            $this->emailSender->send($emailDto);
            
            // If successful, remove from queue
            $this->removeFromQueue(
                $queuedEmail,
                sprintf('Email sent directly (type: %s, id: %d)', $emailType->name, $queuedEmail->getId())
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Direct email sending failed for id %d: %s',
                $queuedEmail->getId(),
                $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Remove an email from the queue
     */
    private function removeFromQueue(EmailQueue $queuedEmail, string $logMessage): void
    {
        $this->emailQueueRepo->remove($queuedEmail);
        $this->logger->info($logMessage);
    }

    /**
     * Handle a failed email - either keep in queue or move to failed_emails
     */
    private function handleFailedEmail(
        EmailQueue $queuedEmail, 
        string $error,
    ): void
    {
        // If max attempts reached, move to failed_emails table
        if ($queuedEmail->getAttempts() >= self::MAX_ATTEMPTS) {
            $failedEmail = $this->emailFactory->createFailedEmail(
                $queuedEmail->getEmailType(),
                $queuedEmail->getContext(),
                $error,
                $queuedEmail->getAttempts(),
                $queuedEmail->getCreatedAt(),
            );
            
            // Copy optional fields if present
            if ($queuedEmail->getLastAttemptAt()) {
                $failedEmail->setLastAttemptAt($queuedEmail->getLastAttemptAt());
            }
            
            if ($queuedEmail->getLocale()) {
                $failedEmail->setLocale($queuedEmail->getLocale());
            }
            
            // Persist the failed email and remove from queue
            $this->failedEmailRepo->save($failedEmail);
            $this->emailQueueRepo->remove($queuedEmail);
            
            $this->logger->error(sprintf(
                'Email moved to failed_emails after %d attempts (type: %s, id: %d): %s',
                $queuedEmail->getAttempts(),
                $queuedEmail->getEmailType(),
                $queuedEmail->getId(),
                $error
            ));
        } else {
            // Just log the failure for emails still under max attempts
            $this->logger->warning(sprintf(
                'Email delivery attempt %d/%d failed (type: %s, id: %d): %s',
                $queuedEmail->getAttempts(),
                self::MAX_ATTEMPTS,
                $queuedEmail->getEmailType(),
                $queuedEmail->getId(),
                $error
            ));
        }
        
        // Flush changes to database
        $this->failedEmailRepo->flush();
        $this->emailQueueRepo->flush();
    }
    
    /**
     * Determine Kafka topic for a given email type
     */
    private function determineKafkaTopic(string $emailType): string
    {
        if (!isset($this->topicMap[$emailType])) {
            throw new \InvalidArgumentException('Unknown email type: ' . $emailType);
        }
        
        return $this->topicMap[$emailType];
    }
}