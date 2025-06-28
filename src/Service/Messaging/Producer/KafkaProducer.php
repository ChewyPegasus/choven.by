<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Psr\Log\LoggerInterface;
use Interop\Queue\Context;
use App\Service\Messaging\Producer\Producer as ProducerInterface;

class KafkaProducer implements ProducerInterface
{
    private Context $context;

    public function __construct(
        private readonly string $bootstrapServers,
        private readonly LoggerInterface $logger,
    )
    {
        $connectionFactory = new RdKafkaConnectionFactory([
            'global' => [
                'bootstrap.servers' => $this->bootstrapServers,
                'socket.timeout.ms' => '5000',
                'metadata.request.timeout.ms' => '5000',
                'message.timeout.ms' => '5000',
            ]
        ]);

        $this->context = $connectionFactory->createContext();
    }

    public function produce(string $topic, string $message, ?string $key = null)
    {
        try {
            $kafkaTopic = $this->context->createTopic($topic);
            $kafkaMessage = $this->context->createMessage($message);

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
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to publish message to Kafka',
                [
                    'topic' => $kafkaTopic,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
            throw $e;
        }
    }
}