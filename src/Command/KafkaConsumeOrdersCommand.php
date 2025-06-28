<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\EmailType;
use App\Factory\EmailFactory;
use App\Repository\OrderRepository;
use App\Service\Messaging\Consumer\KafkaConsumer;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\LocaleSwitcher;

#[AsCommand(
    name: 'app:kafka:consume-orders',
    description: 'Consume messages from the orders Kafka topic',
)]
class KafkaConsumeOrdersCommand extends Command
{
    public function __construct(
        private readonly KafkaConsumer $consumer,
        private readonly string $orderTopic,
        private readonly LoggerInterface $logger,
        private readonly OrderRepository $orderRepository,
        private readonly EmailSender $sender,
        private readonly EmailFactory $emailFactory,
        private readonly LocaleSwitcher $localeSwitcher
    ) {
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

                    try {
                        $messageData = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        $orderId = $messageData['id'] ?? null;

                        if (!$orderId) {
                            $this->logger->warning('Kafka message is missing order ID', ['payload' => $message->getBody()]);
                            continue;
                        }

                        $order = $this->orderRepository->find($orderId);

                        if (!$order) {
                            $this->logger->error('Order not found for ID from Kafka message', ['orderId' => $orderId]);
                            continue;
                        }

                        try {
                            if ($locale = $order->getLocale()) {
                                $this->localeSwitcher->setLocale($locale);
                            }

                            $emailDto = $this->emailFactory->createDTO(EmailType::ORDER_CONFIRMATION, ['order' => $order]);
                            $this->sender->send($emailDto);

                            $output->writeln(sprintf('Email for order %d sent successfully in locale "%s".', $order->getId(), $this->localeSwitcher->getLocale()));
                        } finally {
                            // Resetting locale on the default one not to influence on the next message
                            $this->localeSwitcher->reset();
                        }

                    } catch (\JsonException $e) {
                        $this->logger->error('Failed to decode Kafka message JSON', [
                            'error' => $e->getMessage(),
                            'payload' => $message->getBody(),
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to process order message and send email', [
                            'error' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
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