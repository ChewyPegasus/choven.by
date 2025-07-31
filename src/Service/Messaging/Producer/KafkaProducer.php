<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

use App\Factory\KafkaConnectionFactory;
use Psr\Log\LoggerInterface;
use Interop\Queue\Context;
use App\Service\Messaging\Producer\Producer as ProducerInterface; // Assuming Producer is an interface

/**
 * KafkaProducer class implements the ProducerInterface for producing messages to Kafka.
 *
 * This class handles the connection to Kafka, checks its availability,
 * and publishes raw string messages to specified topics. It is intentionally
 * generic and does not deal with application-specific DTOs or message structures.
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
     * This method first checks Kafka's availability, then sends the provided
     * payload (which is expected to be a ready-to-send string, typically JSON)
     * to the Kafka topic.
     *
     * @param string $topic The Kafka topic to which the message will be published.
     * @param string $payload The raw string payload of the message (e.g., JSON string).
     * @param string|null $key An optional message key for partitioning messages.
     * @throws \Exception If Kafka is unavailable or another error occurs during message production.
     */
    public function produce(string $topic, string $payload, ?string $key = null): void
    {
        try {
            // Check Kafka availability before attempting to send a message
            $this->checkKafkaAvailability();
            
            $kafkaTopic = $this->context->createTopic($topic);

            // Create Kafka message from the provided string payload
            $kafkaMessage = $this->context->createMessage($payload);

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
                    'key' => $key,
                    'payload_length' => strlen($payload),
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to publish message to Kafka',
                [
                    'topic' => $topic,
                    'key' => $key,
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
        // Use error suppression (@) as fsockopen will emit a warning on failure
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
}