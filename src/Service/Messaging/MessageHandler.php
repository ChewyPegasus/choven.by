<?php

namespace App\Service\Messaging;

use App\Entity\Order;
use App\Enum\EmailType;
use App\Factory\EmailFactory;
use App\Service\Sending\EmailSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly EmailSender $emailSender,
        private readonly EmailFactory $emailFactory,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function handleOrderMessage(string $message): void
    {
        try {
            $orderData = json_decode($message, true);

            if (!isset($orderData['id'])) {
                $this->logger->error('Invalid order message format', ['message' => $message]);
                return;
            }

            $order = $this->entityManager->getRepository(Order::class)->find($orderData['id']);

            if (!$order) {
                $this->logger->error('Order not found', ['id' => $orderData['id']]);
                return;
            }

            $this->emailSender->send($this->emailFactory->createDTO(
                EmailType::ORDER_CONFIRMATION,
                [
                    'order' => $order,
                ]
            ));

            $this->logger->info('Order processed successfully', ['id' => $order->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Error handling order message', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    public function handleEmailMessage(string $message): void
    {
        try {
            $mailData = json_decode($message, true);

            if (!isset($emailData['type']) || !isset($emailData['data'])) {
                $this->logger->error('Invalid email message format', ['message' => $message]);
                return;
            }

            $emailType = EmailType::from($emailData['type']);
            $dto = $this->emailFactory->createDTO($emailType, $emailData['data']);

            $this->emailSender->send($dto);

            $this->logger->info('Email sent successfully', ['type' => $emailType->name]);
        } catch (\Exception $e) {
            $this->logger->error('Error handling email message', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}