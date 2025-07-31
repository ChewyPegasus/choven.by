<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor\Topic;

use App\Enum\EmailTemplate;
use App\Exception\OrderNotFoundException;
use App\Factory\EmailFactory;
use App\Repository\Interfaces\OrderRepositoryInterface;
use App\Service\Messaging\Processor\MessageProcessorInterface;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Processes messages related to orders from a messaging queue (e.g., Kafka).
 *
 * This processor is responsible for retrieving order details, creating an email DTO,
 * and sending an order confirmation email. It also handles locale switching for emails.
 */
class OrderMessageProcessor implements MessageProcessorInterface
{
    /**
     * Constructs a new OrderMessageProcessor instance.
     *
     * @param OrderRepositoryInterface $orderRepository The repository for managing Order entities.
     * @param EmailFactory $emailFactory The factory for creating email-related DTOs.
     * @param EmailSender $sender The service for sending emails.
     * @param LocaleSwitcher $localeSwitcher The service for managing the application locale.
     * @param LoggerInterface $logger The logger instance for recording activities and errors.
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EmailFactory $emailFactory,
        private readonly EmailSender $sender,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Processes an order message.
     *
     * This method extracts the order ID from the message data, retrieves the order
     * from the repository, sets the appropriate locale, creates an order confirmation
     * email DTO, and attempts to send the email. It logs success or failure.
     *
     * @param array<string, mixed> $messageData The decoded message data, expected to contain an 'id' key.
     * @param OutputInterface $output The console output interface for displaying messages.
     * @return bool True if the message was processed successfully, false otherwise.
     */
    public function process(array $messageData, OutputInterface $output): bool
    {
        // Reset locale to default before processing each message to ensure isolation
        $this->localeSwitcher->reset();

        $orderId = $messageData['id'] ?? null;
        
        if (!$orderId) {
            $this->logger->warning('Order message is missing ID', ['data' => $messageData]);
            $output->writeln('<error>Order message is missing ID.</error>');
            return false;
        }
        
        try {
            // Use getById which throws an exception if the order is not found
            $order = $this->orderRepository->getById((int) $orderId); // Cast to int as ID is expected to be int
            
            // Set locale if it is present in the order entity
            if ($locale = $order->getLocale()) {
                $this->localeSwitcher->setLocale($locale);
                $this->logger->debug(sprintf('Locale set to "%s" for order %d.', $locale, $order->getId()));
            }
            
            // Create the OrderDTO for email sending
            $emailDto = $this->emailFactory->createDTO(EmailTemplate::ORDER_CONFIRMATION, ['order' => $order]);
            
            // Send the email
            $this->sender->send($emailDto);
            
            $output->writeln(sprintf('Email for order %d sent successfully in locale "%s".', $order->getId(), $this->localeSwitcher->getLocale()));
            $this->logger->info(sprintf('Order confirmation email sent for order ID: %d', $order->getId()));
            
            return true;
        } catch (OrderNotFoundException $e) {
            // Handle specific exception from repository
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return false;
        } catch (\Exception $e) {
            // Catch any other general exceptions during email processing
            $logContext = ['orderId' => $orderId, 'error' => $e->getMessage(), 'exception' => $e];
            if (isset($order) && $order->getId()) {
                $logContext['orderId'] = $order->getId(); // Use actual order ID if available
            }
            $this->logger->error('Failed to process order email', $logContext);
            $output->writeln(sprintf('<error>Failed to send email for order %s: %s</error>', $orderId, $e->getMessage()));
            
            return false;
        }
    }
}