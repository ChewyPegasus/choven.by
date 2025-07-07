<?php

declare(strict_types=1);

namespace App\Service\Messaging\Factory;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Interop\Queue\Context;

class KafkaConnectionFactory
{
    /**
     * Create a connection factory for consumer
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
     * Create a connection factory for producer
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
