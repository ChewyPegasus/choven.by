<?php

declare(strict_types=1);

namespace App\Service\Messaging\Consumer;

use App\Service\Messaging\Consumer\Consumer as ConsumerInterface;
use App\Factory\KafkaConnectionFactory;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Psr\Log\LoggerInterface;

/**
 * KafkaConsumer class implements the ConsumerInterface for consuming messages from Kafka.
 *
 * This class handles the connection to Kafka, subscription to topics, and consumption of messages.
 * It leverages the `php-rdkafka-interop` library for Kafka interactions.
 */
class KafkaConsumer implements ConsumerInterface
{
    private Context $context;
    private ?Consumer $consumer = null;
    
    /**
     * Constructs a new KafkaConsumer instance.
     *
     * Initializes the Kafka context using the provided connection details.
     *
     * @param string $bootstrapServers A comma-separated list of Kafka broker addresses.
     * @param string $groupId The consumer group ID.
     * @param string $autoOffsetReset The auto offset reset policy (e.g., 'earliest', 'latest', 'none').
     * @param LoggerInterface $logger The logger instance for logging messages.
     * @param KafkaConnectionFactory $connectionFactory The factory for creating Kafka connection contexts.
     */
    public function __construct(
        private readonly string $bootstrapServers,
        private readonly string $groupId,
        private readonly string $autoOffsetReset,
        private readonly LoggerInterface $logger,
        private readonly KafkaConnectionFactory $connectionFactory,
    ) {
        $this->context = $this->connectionFactory->createConsumerContext(
            $this->bootstrapServers,
            $this->groupId,
            $this->autoOffsetReset
        );
    }

    /**
     * Subscribes the consumer to a specific Kafka topic.
     *
     * After this method is called, the `consume` method can be used to read messages.
     *
     * @param string $topic The name of the Kafka topic to subscribe to.
     */
    public function subscribe(string $topic): void
    {
        $kafkaTopic = $this->context->createTopic($topic);
        $this->consumer = $this->context->createConsumer($kafkaTopic);
        $this->logger->info(sprintf('Subscribed to Kafka topic: %s', $topic));
    }

    /**
     * Consumes a single message from the subscribed topic(s).
     *
     * This method attempts to receive a message within the specified timeout.
     * If a message is received, it is logged and acknowledged before being returned.
     * Error during consumption are logged.
     *
     * @param int $timeout The maximum time (in milliseconds) to wait for a message. Defaults to 5000ms.
     * @return Message|null The consumed message, or null if no message was available within the timeout or an error occurred.
     * @throws \RuntimeException If `subscribe` has not been called before `consume`.
     */
    public function consume(int $timeout = 5000): ?Message
    {
        if (!$this->consumer) {
            throw new \RuntimeException('No topic subscribed. Call subscribe method first.');
        }

        try {
            $message = $this->consumer->receive($timeout);

            if ($message) {
                $this->logger->info(
                    'Message consumed from Kafka',
                    [
                        'body' => $message->getBody(),
                        'topic' => $message->getProperties()['topicName'] ?? 'unknown', // Attempt to get topic name
                        'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                    ],
                );

                $this->consumer->acknowledge($message); // Acknowledge message after successful consumption
                $this->logger->debug('Message acknowledged.');

                return $message;
            }

            $this->logger->debug(sprintf('No message received within %dms timeout.', $timeout));
            return null;
        } catch (\Exception $e) {
            $this->logger->error(
                'Error consuming message from Kafka',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                ],
            );

            // In case of an error, it might be necessary to reject the message,
            // depending on the desired behavior for unprocessable messages.
            // For now, it simply returns null and logs the error.
            return null;
        }
    }
}