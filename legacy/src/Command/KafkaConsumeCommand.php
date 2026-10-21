<?php

declare(strict_types=1);

namespace App\Command;

use App\Factory\EmailFactory;
use App\Repository\Interfaces\OrderRepositoryInterface;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Service\Messaging\Consumer\KafkaConsumer;
use App\Service\Messaging\Processor\MessageProcessorRegistry;
use App\Service\Messaging\Processor\Topic\OrderMessageProcessor;
use App\Service\Messaging\Processor\Topic\VerificationMessageProcessor;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Symfony console command to consume messages from Kafka topics.
 *
 * This command initializes and runs a Kafka consumer to listen for messages
 * on configured topics, such as 'order' and 'verification'. It dispatches
 * received messages to appropriate processors based on their type.
 */
#[AsCommand(
    name: 'app:kafka:consume',
    description: 'Consume messages from Kafka topics',
)]
class KafkaConsumeCommand extends Command
{
    private const CONSUME_TIMEOUT = 10000;

    /**
     * @param array<string, string> $topics An associative array of Kafka topic names.
     */
    public function __construct(
        private readonly KafkaConsumer $consumer,
        private readonly array $topics,
        private readonly LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailSender $sender,
        private readonly EmailFactory $emailFactory,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly MessageProcessorRegistry $processorRegistry,
    ) {
        parent::__construct();
        $this->initProcessorRegistry();
    }

    /**
     * Configures the command, setting its help message.
     */
    protected function configure(): void
    {
        $this->setHelp('This command starts a consumer for Kafka topics (orders and registrations)');
    }

    /**
     * Executes the Kafka consumer command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeConsumer($output);

        foreach ($this->topics as $topicName => $topicValue) {
            if (!$this->processSingleTopic($output, $topicValue)) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Initializes the consumer and resets locale.
     */
    private function initializeConsumer(OutputInterface $output): void
    {
        $output->writeln('Starting Kafka consumer for multiple topics...');
        $this->localeSwitcher->reset();
    }

    /**
     * Processes messages from a single Kafka topic.
     */
    private function processSingleTopic(OutputInterface $output, string $topic): bool
    {
        try {
            $this->subscribeToTopic($output, $topic);
            return $this->consumeMessages($output, $topic);
        } catch (\Exception $e) {
            $this->handleTopicProcessingError($output, $topic, $e);
            return false;
        }
    }

    /**
     * Subscribes to the given topic.
     */
    private function subscribeToTopic(OutputInterface $output, string $topic): void
    {
        $output->writeln(sprintf('Processing topic: %s', $topic));
        $this->consumer->subscribe($topic);
    }

    /**
     * Continuously consumes messages from the topic.
     */
    private function consumeMessages(OutputInterface $output, string $topic): bool
    {
        while (true) {
            $message = $this->consumer->consume(self::CONSUME_TIMEOUT);

            if (!$message) {
                continue; // No message received within timeout, continue loop
            }

            $this->processMessage($output, $topic, $message);
            return true; // Continue processing after a message
        }
    }

    /**
     * Processes a single message.
     */
    private function processMessage(OutputInterface $output, string $topic, $message): void
    {
        $output->writeln(sprintf('Received message from topic: %s', $topic));

        try {
            $messageData = $this->decodeMessage($message);
            $this->handleMessageByType($output, $messageData);
        } catch (\JsonException $e) {
            $this->handleJsonDecodeError($output, $message, $e);
        } catch (\Exception $e) {
            $this->handleMessageProcessingError($output, $e);
        }
    }

    /**
     * Decodes JSON message.
     */
    private function decodeMessage($message): array
    {
        return json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Handles message processing based on its type.
     */
    private function handleMessageByType(OutputInterface $output, array $messageData): void
    {
        $messageType = $messageData['type'] ?? 'unknown';

        if (!$this->processorRegistry->hasProcessor($messageType)) {
            $this->handleUnknownMessageType($output, $messageType, $messageData);
            return;
        }

        $this->processMessageWithProcessor($output, $messageType, $messageData);
    }

    /**
     * Processes message with the appropriate processor.
     */
    private function processMessageWithProcessor(OutputInterface $output, string $messageType, array $messageData): void
    {
        $processor = $this->processorRegistry->getProcessor($messageType);
        $result = $processor->process($messageData, $output);

        $status = $result ? 'Successfully processed' : '<error>Failed to process</error>';
        $output->writeln(sprintf('%s %s message', $status, $messageType));
    }

    /**
     * Handles unknown message type.
     */
    private function handleUnknownMessageType(OutputInterface $output, string $messageType, array $messageData): void
    {
        $this->logger->warning('Unknown message type received', [
            'type' => $messageType,
            'data' => $messageData,
        ]);
        $output->writeln(sprintf('<warning>Unknown message type: %s</warning>', $messageType));
    }

    /**
     * Handles JSON decode errors.
     */
    private function handleJsonDecodeError(OutputInterface $output, $message, \JsonException $e): void
    {
        $this->logger->error('Failed to decode message JSON', [
            'error' => $e->getMessage(),
            'payload' => $message->getBody(),
        ]);
        $output->writeln('<error>JSON decode error: ' . $e->getMessage() . '</error>');
    }

    /**
     * Handles general message processing errors.
     */
    private function handleMessageProcessingError(OutputInterface $output, \Exception $e): void
    {
        $this->logger->error('Error processing message', [
            'error' => $e->getMessage(),
            'exception' => $e,
        ]);
        $output->writeln('<error>Processing error: ' . $e->getMessage() . '</error>');
    }

    /**
     * Handles topic processing errors.
     */
    private function handleTopicProcessingError(OutputInterface $output, string $topic, \Exception $e): void
    {
        $this->logger->error('Error processing topic', [
            'topic' => $topic,
            'error' => $e->getMessage(),
            'exception' => $e,
        ]);
        $output->writeln('<error>' . $e->getMessage() . '</error>');
    }

    /**
     * Initializes the message processor registry.
     */
    private function initProcessorRegistry(): void
    {
        $this->registerOrderProcessor();
        $this->registerVerificationProcessor();
    }

    /**
     * Registers the order message processor.
     */
    private function registerOrderProcessor(): void
    {
        $this->processorRegistry->registerProcessor(
            'order',
            new OrderMessageProcessor(
                $this->orderRepository,
                $this->emailFactory,
                $this->sender,
                $this->localeSwitcher,
                $this->logger
            )
        );
    }

    /**
     * Registers the verification message processor.
     */
    private function registerVerificationProcessor(): void
    {
        $this->processorRegistry->registerProcessor(
            'verification',
            new VerificationMessageProcessor(
                $this->userRepository,
                $this->emailFactory,
                $this->sender,
                $this->logger,
                $this->localeSwitcher,
            )
        );
    }
}