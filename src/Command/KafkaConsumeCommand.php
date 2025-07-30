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

#[AsCommand(
    name: 'app:kafka:consume',
    description: 'Consume messages from Kafka topics',
)]
class KafkaConsumeCommand extends Command
{
    /**
     * @param array<string, string> $topics
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
        
        // Initialize registry
        $this->initProcessorRegistry();
    }
    
    private function initProcessorRegistry(): void
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

    protected function configure(): void
    {
        $this->setHelp('This command starts a consumer for Kafka topics (orders and registrations)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting Kafka consumer for multiple topics...');
        $this->localeSwitcher->reset();

        foreach ($this->topics as $topicName => $topicValue) {
            if (!$this->processTopic($input, $output, $topicValue)) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function processTopic(InputInterface $input, OutputInterface $output, string $topic): bool
    {
        try {
            $output->writeln(sprintf('Processing topic: %s', $topic));
            $this->consumer->subscribe($topic);
            
            while (true) {
                $message = $this->consumer->consume(10000);
                
                if ($message) {
                    $output->writeln(sprintf('Received message from topic: %s', $topic));
                    
                    try {
                        $messageData = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        
                        $messageType = $messageData['type'] ?? 'unknown';
                        
                        // Find and call proper processor
                        if ($this->processorRegistry->hasProcessor($messageType)) {
                            $processor = $this->processorRegistry->getProcessor($messageType);
                            $result = $processor->process($messageData, $output);
                            
                            if ($result) {
                                $output->writeln(sprintf('Successfully processed %s message', $messageType));
                            } else {
                                $output->writeln(sprintf('<error>Failed to process %s message</error>', $messageType));
                            }
                        } else {
                            $this->logger->warning('Unknown message type received', [
                                'type' => $messageType,
                                'data' => $messageData,
                            ]);
                            $output->writeln(sprintf('<warning>Unknown message type: %s</warning>', $messageType));
                        }
                        
                    } catch (\JsonException $e) {
                        $this->logger->error('Failed to decode message JSON', [
                            'error' => $e->getMessage(),
                            'payload' => $message->getBody(),
                        ]);
                        $output->writeln('<error>JSON decode error: ' . $e->getMessage() . '</error>');
                    } catch (\Exception $e) {
                        $this->logger->error('Error processing message', [
                            'error' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                        $output->writeln('<error>Processing error: ' . $e->getMessage() . '</error>');
                    }
                    
                    return true;
                } else {
                    return true;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing topic', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            
            return false;
        }
    }
}