<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor\Topic;

use App\Enum\EmailTemplate;
use App\Factory\EmailFactory;
use App\Repository\Interfaces\OrderRepositoryInterface;
use App\Service\Messaging\Processor\MessageProcessorInterface;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\LocaleSwitcher;

class OrderMessageProcessor implements MessageProcessorInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EmailFactory $emailFactory,
        private readonly EmailSender $sender,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger
    ) {
    }
    
    public function process(array $messageData, OutputInterface $output): bool
    {
        $this->localeSwitcher->reset();

        $orderId = $messageData['id'] ?? null;
        
        if (!$orderId) {
            $this->logger->warning('Order message is missing ID', ['data' => $messageData]);
            
            return false;
        }
        
        $order = $this->orderRepository->find($orderId);
        
        if (!$order) {
            $this->logger->error('Order not found', ['orderId' => $orderId]);
            
            return false;
        }
        
        try {
            // Set locale if it is present
            if ($locale = $order->getLocale()) {
                $this->localeSwitcher->setLocale($locale);
            }
            
            $emailDto = $this->emailFactory->createDTO(EmailTemplate::ORDER_CONFIRMATION, ['order' => $order]);
            $this->sender->send($emailDto);
            
            $output->writeln(sprintf('Email for order %d sent successfully in locale "%s".', $order->getId(), $this->localeSwitcher->getLocale()));
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process order email', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            
            return false;
        }
    }
}