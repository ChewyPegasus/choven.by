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
     * This method is responsible for serializing the provided DTO data
     * and sending it to the designated messaging topic. An optional key
     * can be provided, which is often used for message partitioning
     * in systems like Kafka.
     *
     * @param string $topic The name of the topic to which the message will be sent.
     * @param AbstractEmailDTO $dto The data transfer object containing the message payload.
     * @param string|null $key An optional key for the message, often used for partitioning.
     * @return void
     * @throws \Exception If the message cannot be produced (e.g., connection error, serialization error).
     */
    public function produce(string $topic, AbstractEmailDTO $dto, ?string $key = null): void;
}