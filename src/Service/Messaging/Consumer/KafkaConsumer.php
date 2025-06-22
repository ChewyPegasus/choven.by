<?php

declare(strict_types=1);

namespace App\Service\Messaging\Consumer;

use App\Service\Messaging\Consumer\Consumer as ConsumerInterface;
use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Psr\Log\LoggerInterface;

class KafkaConsumer implements ConsumerInterface
{
    private Context $context;
    private ?Consumer $consumer = null;
    
    public function __construct(
        private readonly string $bootstrapServers,
        private readonly string $groupId,
        private readonly string $autoOffsetReset,
        private readonly LoggerInterface $logger,
    )
    {
        $connectionFactory = new RdKafkaConnectionFactory([
            'global' => [
                'bootstrap.servers' => $this->bootstrapServers,
                'group.id' => $this->groupId,
                'auto.offset.reset' => $this->autoOffsetReset,
            ]
        ]);

        $this->context = $connectionFactory->createContext();
    }

    public function subscribe(string $topic): void
    {
        $kafkaTopic = $this->context->createTopic($topic);
        $this->consumer = $this->context->createConsumer($kafkaTopic);
    }

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
                    ],
                );

                $this->consumer->acknowledge($message);

                return $message;
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error(
                'Error consuming message from Kafka',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ],
            );

            return null;
        }
    }
}