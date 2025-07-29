<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\DTO;
use App\DTO\OrderDTO;
use App\DTO\VerificationDTO;
use App\Entity\EmailQueue;
use App\Entity\FailedEmail;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\EmailTemplate;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailFactory
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function createDTO(EmailTemplate $type, array $data): DTO
    {
        return match($type) {
            EmailTemplate::ORDER_CONFIRMATION => $this->createOrderDTO($data),
            EmailTemplate::VERIFICATION => $this->createVerificationDTO($data),
        };
    }

    private function createOrderDTO(array $data): OrderDTO
    {
        $order = $data['order'];
        
        // If the order ID is passed instead of the object
        if (is_numeric($order)) {
            $order = $this->orderRepository->find($order);
            if (!$order) {
                throw new \InvalidArgumentException('Order not found with ID: ' . $data['order']);
            }
        }
        
        return new OrderDTO($order);
    }

    private function createVerificationDTO(array $data): VerificationDTO
    {
        $user = $data['user'];
        
        // If the user ID is passed instead of the object
        if (is_numeric($user)) {
            $user = $this->userRepository->find($user);
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

    public function createEmailQueue(
        string $emailType,
        array $context,
        ?string $locale, 
    ): EmailQueue 
    {
        $email = new EmailQueue();
        $email->setEmailType($emailType)
            ->setContext($context)
            ->setLocale($locale)
        ;
        
        return $email;
    }

    public function createFailedEmail(
        string $emailType,
        array $context,
        ?string $error,
        int $attempts,
        DateTimeImmutable $createdAt,
    ): FailedEmail
    {
        $email = new FailedEmail;
        $email->setEmailType($emailType)
            ->setContext($context)
            ->setError($error)
            ->setAttempts($attempts)
            ->setCreatedAt($createdAt)
        ;
        
        return $email;
    }
}