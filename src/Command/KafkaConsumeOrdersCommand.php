<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Messaging\Consumer\KafkaConsumer;
use App\Service\Messaging\MessageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:kafka:consume-orders',
    description: 'Consume messages from the orders Kafka topic',
)]
class KafkaConsumeOrdersCommand extends Command
{
    public function __construct(
        private readonly KafkaConsumer $consumer,
        private readonly MessageHandler $messageHandler,
        private readonly string $orderTopic,
        private readonly LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command starts a consumer for the orders Kafka topic');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting Kafka consumer for orders topic...');

        try {
            $this->consumer->subscribe($this->orderTopic);

            $output->writeln(sprintf('Subscribed to topic: %s', $this->orderTopic));

            while (true) {
                $message = $this->consumer->consume(10000);

                if ($message) {
                    $output->writeln('Received message from Kafka');

                    $this->messageHandler->handleOrderMessage($message->getBody());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in Kafka consumer command', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}