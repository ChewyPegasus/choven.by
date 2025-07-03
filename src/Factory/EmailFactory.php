<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\DTO;
use App\DTO\OrderDTO;
use App\DTO\VerificationDTO;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\EmailType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function createDTO(EmailType $type, array $data): DTO
    {
        return match($type) {
            EmailType::ORDER_CONFIRMATION => $this->createOrderDTO($data),
            EmailType::VERIFICATION => $this->createVerificationDTO($data),
        };
    }

    private function createOrderDTO(array $data): OrderDTO
    {
        $order = $data['order'];
        
        // Если передан ID заказа вместо объекта
        if (is_numeric($order)) {
            $order = $this->entityManager->getRepository(Order::class)->find($order);
            if (!$order) {
                throw new \InvalidArgumentException('Order not found with ID: ' . $data['order']);
            }
        }
        
        return new OrderDTO($order);
    }

    private function createVerificationDTO(array $data): VerificationDTO
    {
        $user = $data['user'];
        
        // Если передан ID пользователя вместо объекта
        if (is_numeric($user)) {
            $user = $this->entityManager->getRepository(User::class)->find($user);
            if (!$user) {
                throw new \InvalidArgumentException('User not found with ID: ' . $data['user']);
            }
        }
        
        return new VerificationDTO(
            $user,
            $data['confirmUrl'],
            $data['locale'] ?? null,
        );
    }

    public function createEmail(array $emailData, string $recipientEmail): Email
    {
        return (new Email())
            ->from(new Address($emailData['sender_email'], $emailData['sender_name']))
            ->to($recipientEmail)
            ->subject($emailData['subject'])
            ->html($emailData['html_content'])
            ->text($emailData['text_content']);
    }
}