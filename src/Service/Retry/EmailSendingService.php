<?php

declare(strict_types=1);

namespace App\Service\Retry;

use App\Enum\EmailTemplate;
use App\Service\Messaging\Producer\EmailKafkaMessageFactory;
use App\Service\Messaging\Producer\Producer;
use App\Service\Sending\EmailSender;
use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Service for sending emails using various mechanisms.
 *
 * This service encapsulates the logic for sending emails, providing
 * a unified interface for the EmailRetryService. It attempts to send
 * via Kafka first, with a fallback to direct email sending if Kafka fails.
 */
class EmailSendingService
{
    /**
     * @var array<string, string> An associative array mapping email types (string values of `EmailTemplate`) to Kafka topic names.
     */
    private array $topicMap;

    /**
     * Constructs a new EmailSendingService instance.
     *
     * @param Producer $producer The Kafka producer service for sending messages to Kafka topics.
     * @param EmailSender $emailSender The service for direct email sending via Symfony Mailer.
     * @param LoggerInterface $logger The logger instance for recording operational logs and errors.
     * @param array<string, string> $topicMap An associative array mapping email types (string values of `EmailTemplate`) to Kafka topic names.
     */
    public function __construct(
        private readonly Producer $producer,
        private readonly EmailSender $emailSender,
        private readonly LoggerInterface $logger,
        array $topicMap, // Passed from config
        private readonly EmailKafkaMessageFactory $emailKafkaMessageFactory,
    ) {
        $this->topicMap = $topicMap;
    }

    /**
     * Attempts to send an email using preferred methods (Kafka, then direct).
     *
     * @param EmailTemplate $emailType The enum instance of the email template.
     * @param object $emailDto The DTO representing the email content to be sent. (e.g., instance of AbstractEmailDTO)
     * @param int|null $queuedEmailId The ID of the email in the queue, for logging purposes.
     * @return bool True if the email was successfully sent by any method, false otherwise.
     */
    public function send(EmailTemplate $emailType, object $emailDto, ?int $queuedEmailId = null): bool
    {
        // Try sending via Kafka first
        if ($this->sendViaKafka($emailType, $emailDto, $queuedEmailId)) {
            return true;
        }

        // If Kafka failed, try sending directly
        if ($this->sendDirectly($emailType, $emailDto, $queuedEmailId)) {
            return true;
        }

        return false;
    }

    /**
     * Attempts to send an email message via Kafka.
     *
     * This is the preferred method of sending.
     *
     * @param EmailTemplate $emailType The enum instance of the email template.
     * @param object $emailDto The DTO representing the email content to be sent.
     * @param int|null $queuedEmailId The ID of the email in the queue, for logging purposes.
     * @return bool True if the email was successfully published to Kafka, false otherwise.
     */
    private function sendViaKafka(EmailTemplate $emailType, object $emailDto, ?int $queuedEmailId): bool
    {
        try {
            // Determine the appropriate Kafka topic for this email type
            $topic = $this->determineKafkaTopic($emailType);

            // Create the Kafka message payload
            $payload = $this->emailKafkaMessageFactory->createPayload($emailDto);
            
            // Send the email DTO to Kafka
            $this->producer->produce(
                $topic,
                $payload,
                'retry_' . ($queuedEmailId ?? uniqid('email_')) // Use queue ID or a unique ID for the message
            );
            
            $this->logger->info(sprintf(
                'Email %s (type: %s) successfully published to Kafka topic "%s".',
                $queuedEmailId ? 'ID ' . $queuedEmailId : 'DTO', $emailType->name, $topic
            ));
            
            return true;
        } catch (JsonException $e) {
            $this->logger->error(sprintf(
                'Failed to prepare JSON payload for email %s (type: %s) for Kafka: %s',
                $queuedEmailId ? 'ID ' . $queuedEmailId : 'DTO',
                $emailType->name,
                $e->getMessage()
            ), ['exception' => $e, 'email_id' => $queuedEmailId, 'email_type' => $emailType->name]);
            return false;
        } catch (\Exception $e) { // Catch other exceptions from producer
            $this->logger->warning(sprintf(
                'Kafka delivery failed for email %s (type: %s): %s',
                $queuedEmailId ? 'ID ' . $queuedEmailId : 'DTO',
                $emailType->name,
                $e->getMessage()
            ), ['exception' => $e, 'email_id' => $queuedEmailId, 'email_type' => $emailType->name]);
            
            return false;
        }
    }
    
    /**
     * Attempts to send an email directly using the `EmailSender` service.
     *
     * This serves as a fallback mechanism if sending via Kafka fails or is not desired.
     *
     * @param EmailTemplate $emailType The enum instance of the email template.
     * @param object $emailDto The DTO representing the email content to be sent.
     * @param int|null $queuedEmailId The ID of the email in the queue, for logging purposes.
     * @return bool True if the email was sent directly, false otherwise.
     */
    private function sendDirectly(EmailTemplate $emailType, object $emailDto, ?int $queuedEmailId): bool
    {
        try {
            // Send the email directly using the EmailSender service
            $this->emailSender->send($emailDto);
            
            $this->logger->info(sprintf(
                'Email %s (type: %s) successfully sent directly.',
                $queuedEmailId ? 'ID ' . $queuedEmailId : 'DTO', $emailType->name
            ));
            
            return true;
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'Direct email sending failed for email %s (type: %s): %s',
                $queuedEmailId ? 'ID ' . $queuedEmailId : 'DTO',
                $emailType->name,
                $e->getMessage()
            ), ['exception' => $e, 'email_id' => $queuedEmailId, 'email_type' => $emailType->name]);
            
            return false;
        }
    }

    /**
     * Determines the appropriate Kafka topic name for a given email type.
     *
     * This method looks up the `emailType` in the `$topicMap` injected into the service.
     *
     * @param EmailTemplate $emailType The EmailTemplate enum instance.
     * @return string The Kafka topic name.
     * @throws \InvalidArgumentException If no topic is mapped for the given email type.
     */
    private function determineKafkaTopic(EmailTemplate $emailType): string
    {
        if (!isset($this->topicMap[$emailType->value])) {
            $this->logger->error(sprintf('No Kafka topic configured for email type: %s', $emailType->name));
            throw new \InvalidArgumentException('Unknown email type or no Kafka topic configured for: ' . $emailType->name);
        }
        
        return $this->topicMap[$emailType->value];
    }
}