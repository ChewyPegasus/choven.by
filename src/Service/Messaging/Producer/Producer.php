<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

use App\DTO\AbstractEmailDTO;

/**
 * Interface for a generic message producer.
 *
 * Defines the contract for services that produce messages to a messaging system,
 * such as Kafka. Implementations should provide a method for sending messages
 * to a specified topic with associated data.
 */
interface Producer
{
    /**
     * Produces a message to a specified topic.
     *
     * Serializes and sends the provided payload to the given messaging topic.
     * An optional key can be specified for message partitioning.
     *
     * @param string $topic The topic to send the message to.
     * @param string $payload The serialized message payload.
     * @param string|null $key Optional key for partitioning.
     * @return void
     * @throws \Exception If the message cannot be produced.
     */
    public function produce(string $topic, string $payload, ?string $key = null): void;
}