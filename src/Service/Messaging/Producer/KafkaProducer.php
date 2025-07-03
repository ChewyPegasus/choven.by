<?php

declare(strict_types=1);

namespace App\Service\Messaging\Producer;

use App\DTO\AbstractEmailDTO;
use App\DTO\OrderDTO;
use App\DTO\VerificationDTO;
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

    /**
     * @throws \Exception If Kafka is unavailable or another error occurs
     */
    public function produce(string $topic, AbstractEmailDTO $dto, ?string $key = null)
    {
        try {
            // Check Kafka availability before sending
            $this->checkKafkaAvailability();
            
            $kafkaTopic = $this->context->createTopic($topic);

            $messageData = [
                'type' => $this->getDTOType($dto),
                'id' => $dto->getContext()['id'],
            ];

            $this->addDTOSpecificData(
                $messageData,
                $dto,
            );

            $kafkaMessage = $this->context->createMessage(
                json_encode($messageData, JSON_THROW_ON_ERROR),
            );

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
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to publish message to Kafka',
                [
                    'topic' => $topic,
                    'error' => $e->getMessage(),
                    'type' => get_class($e),
                ]
            );

            throw $e;
        }
    }

    /**
     * Checks Kafka availability
     * 
     * @throws \Exception If Kafka is unavailable
     */
    private function checkKafkaAvailability(): void
    {
        // Parse connection string to get host and port
        $servers = explode(',', $this->bootstrapServers);
        $server = $servers[0]; // Take the first server from the list
        
        // Remove protocol if it exists
        if (strpos($server, '://') !== false) {
            $server = explode('://', $server)[1];
        }
        
        // Split host and port
        list($host, $port) = explode(':', $server);
        
        // Try to connect to the server
        $socket = @fsockopen($host, (int)$port, $errno, $errstr, 2); // 2 seconds timeout
        
        if (!$socket) {
            $this->logger->error("Kafka connection error: $errstr ($errno)");
            throw new \Exception("Failed to connect to Kafka at $host:$port: $errstr ($errno)");
        }
        
        fclose($socket);
    }

    private function getDTOType(AbstractEmailDTO $dto): string
    {
        return match(true) {
            $dto instanceof OrderDTO => 'order',
            $dto instanceof VerificationDTO => 'verification',
            default => 'unknown',
        };
    }

    private function addDTOSpecificData(array &$messageData, AbstractEmailDTO $dto): void
    {
        if ($dto instanceof VerificationDTO) {
            $messageData['confirmUrl'] = $dto->getConfirmUrl();
            $messageData['locale'] = $dto->getLocale();
        }
    }
}