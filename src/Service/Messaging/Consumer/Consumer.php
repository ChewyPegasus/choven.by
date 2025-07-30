<?php

declare(strict_types=1);

namespace App\Service\Messaging\Consumer;

use Interop\Queue\Message;

/**
 * Interface for a generic message consumer.
 *
 * Defines the contract for services that consume messages from a messaging system,
 * such as Kafka. Implementations should provide methods for subscribing to topics
 * and consuming messages with an optional timeout.
 */
interface Consumer
{
    /**
     * Subscribes the consumer to a specific topic.
     *
     * After calling this method, the consumer is ready to receive messages
     * from the specified topic.
     *
     * @param string $topic The name of the topic to subscribe to.
     */
    public function subscribe(string $topic): void;

    /**
     * Consumes a single message from the subscribed topic(s).
     *
     * This method attempts to retrieve a message within a given timeout period.
     * It should return the message if one is available, or null if no message
     * is received within the timeout.
     *
     * @param int $timeout The maximum time (in milliseconds) to wait for a message. Defaults to 5000ms.
     * @return Message|null The consumed message, or null if no message was available within the timeout.
     */
    public function consume(int $timeout = 5000): ?Message;
}