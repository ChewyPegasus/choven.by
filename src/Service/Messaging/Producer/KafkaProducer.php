<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

use App\DTO\AbstractEmailDTO;
use App\DTO\OrderDTO;
use App\DTO\VerificationDTO;
use App\Factory\KafkaConnectionFactory;
use Psr\Log\LoggerInterface;
use Interop\Queue\Context;
use App\Service\Messaging\Producer\Producer as ProducerInterface;

/**
 * KafkaProducer class implements the ProducerInterface for producing messages to Kafka.
 *
 * This class handles the connection to Kafka, checks its availability,
 * and publishes messages based on different DTO types.
 */
class KafkaProducer implements ProducerInterface
{
    private Context $context;

    /**
     * Constructs a new KafkaProducer instance.
     *
     * Initializes the Kafka context using the provided connection details.
     *
     * @param string $bootstrapServers A comma-separated list of Kafka broker addresses.
     * @param LoggerInterface $logger The logger instance for logging messages.
     * @param KafkaConnectionFactory $connectionFactory The factory for creating Kafka connection contexts.
     */
    public function __construct(
        private readonly string $bootstrapServers,
        private readonly LoggerInterface $logger,
        private readonly KafkaConnectionFactory $connectionFactory,
    ) {
        // Create the producer context with a 5000ms (5 seconds) timeout for operations.
        $this->context = $this->connectionFactory->createProducerContext(
            $this->bootstrapServers,
            5000
        );
    }

    /**
     * Produces a message to a specified Kafka topic.
     *
     * This method first checks Kafka's availability, then constructs the message payload
     * based on the provided DTO type, and finally sends the message to Kafka.
     *
     * @param string $topic The Kafka topic to which the message will be published.
     * @param AbstractEmailDTO $dto The data transfer object containing email-related information.
     * @param string|null $key An optional message key for partitioning messages.
     * @throws \Exception If Kafka is unavailable or another error occurs during message production.
     */
    public function produce(string $topic, AbstractEmailDTO $dto, ?string $key = null): void // Added void return type as per interface
    {
        try {
            // Check Kafka availability before attempting to send a message
            $this->checkKafkaAvailability();
            
            $kafkaTopic = $this->context->createTopic($topic);

            // Prepare common message data
            $messageData = [
                'type' => $this->getDTOType($dto),
                'id' => $dto->getContext()['id'], // Assumes all DTOs have 'id' in their context
            ];

            // Add DTO-specific data to the message
            $this->addDTOSpecificData(
                $messageData,
                $dto,
            );

            // Create Kafka message from JSON encoded data
            $kafkaMessage = $this->context->createMessage(
                json_encode($messageData, JSON_THROW_ON_ERROR),
            );

            // Set message key if provided
            if ($key !== null) {
                $kafkaMessage->setProperty('key', $key);
            }

            $producer = $this->context->createProducer();
            $producer->send($kafkaTopic, $kafkaMessage);

            $this->logger->info(
                'Message published to Kafka',
                [
                    'topic' => $topic,
                    'type' => $messageData['type'],
                    'key' => $key,
                    'message_id' => $messageData['id'], // Log the ID of the entity
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to publish message to Kafka',
                [
                    'topic' => $topic,
                    'dto_type' => get_class($dto),
                    'dto_id' => $dto->getContext()['id'] ?? 'N/A',
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                ]
            );

            // Re-throw the exception to signal failure to the caller
            throw $e;
        }
    }

    /**
     * Checks Kafka availability by attempting a socket connection to the first bootstrap server.
     *
     * @throws \Exception If Kafka is unavailable (socket connection fails).
     */
    private function checkKafkaAvailability(): void
    {
        // Parse connection string to get host and port
        $servers = explode(',', $this->bootstrapServers);
        $server = $servers[0]; // Take the first server from the list as a representative
        
        // Remove protocol (e.g., 'kafka://') if it exists
        if (str_contains($server, '://')) {
            [, $server] = explode('://', $server, 2);
        }
        
        // Split host and port, handling cases where port might be missing (default to Kafka default)
        $port = 9092; // Default Kafka port
        if (str_contains($server, ':')) {
            list($host, $port) = explode(':', $server, 2);
            $port = (int)$port;
        } else {
            $host = $server;
        }
        
        // Try to connect to the server with a short timeout
        $socket = @fsockopen($host, $port, $errno, $errstr, 2); // 2 seconds timeout
        
        if (!$socket) {
            $this->logger->error(
                'Kafka connection error',
                [
                    'host' => $host,
                    'port' => $port,
                    'error_code' => $errno,
                    'error_message' => $errstr,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                ]
            );
            throw new \Exception("Failed to connect to Kafka at $host:$port: $errstr ($errno)");
        }
        
        fclose($socket);
        $this->logger->debug(sprintf('Successfully connected to Kafka at %s:%d.', $host, $port));
    }

    /**
     * Determines the message type string based on the provided DTO instance.
     *
     * @param AbstractEmailDTO $dto The email DTO.
     * @return string The type of the DTO (e.g., 'order', 'verification', 'unknown').
     */
    private function getDTOType(AbstractEmailDTO $dto): string
    {
        return match(true) {
            $dto instanceof OrderDTO => 'order',
            $dto instanceof VerificationDTO => 'verification',
            default => 'unknown',
        };
    }

    /**
     * Adds DTO-specific data to the message payload.
     *
     * @param array<string, mixed> &$messageData The message data array to modify by reference.
     * @param AbstractEmailDTO $dto The email DTO from which to extract specific data.
     */
    private function addDTOSpecificData(array &$messageData, AbstractEmailDTO $dto): void
    {
        if ($dto instanceof VerificationDTO) {
            $messageData['confirmUrl'] = $dto->getConfirmUrl();
            $messageData['locale'] = $dto->getLocale();
        }
        // Add more DTO-specific data for other DTO types as needed
    }
}