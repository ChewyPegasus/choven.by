<?php

declare(strict_types=1);

namespace App\Factory;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Interop\Queue\Context;

/**
 * Factory for creating Kafka connection contexts for both consumers and producers.
 *
 * This factory abstracts the creation of `Interop\Queue\Context` objects
 * specifically configured for Apache Kafka using the `Enqueue\RdKafka` library,
 * allowing for different configurations based on whether the context is for
 * consuming or producing messages.
 */
class KafkaConnectionFactory
{
    /**
     * Creates a connection context configured for a Kafka consumer.
     *
     * Sets up global Kafka properties necessary for a consumer, including
     * bootstrap servers, consumer group ID, and auto offset reset policy.
     *
     * @param string $bootstrapServers A comma-separated list of Kafka broker host:port.
     * @param string $groupId The consumer group ID.
     * @param string $autoOffsetReset The auto offset reset policy (e.g., 'earliest', 'latest', 'none').
     * @return Context The Kafka context configured for a consumer.
     */
    public function createConsumerContext(
        string $bootstrapServers,
        string $groupId,
        string $autoOffsetReset
    ): Context {
        $connectionFactory = new RdKafkaConnectionFactory([
            'global' => [
                'bootstrap.servers' => $bootstrapServers,
                'group.id' => $groupId,
                'auto.offset.reset' => $autoOffsetReset,
            ]
        ]);

        return $connectionFactory->createContext();
    }

    /**
     * Creates a connection context configured for a Kafka producer.
     *
     * Sets up global Kafka properties necessary for a producer, including
     * bootstrap servers and various timeout settings for reliable message sending.
     *
     * @param string $bootstrapServers A comma-separated list of Kafka broker host:port.
     * @param int $timeout The timeout in milliseconds for socket, metadata requests, and message timeouts.
     * @return Context The Kafka context configured for a producer.
     */
    public function createProducerContext(
        string $bootstrapServers,
        int $timeout = 5000
    ): Context {
        $connectionFactory = new RdKafkaConnectionFactory([
            'global' => [
                'bootstrap.servers' => $bootstrapServers,
                'socket.timeout.ms' => (string)$timeout,
                'metadata.request.timeout.ms' => (string)$timeout,
                'message.timeout.ms' => (string)$timeout,
            ]
        ]);

        return $connectionFactory->createContext();
    }
}