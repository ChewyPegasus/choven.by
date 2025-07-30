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
use App\Repository\Interfaces\OrderRepositoryInterface;
use App\Repository\Interfaces\UserRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Factory for creating various email-related objects, including DTOs,
 * Symfony Mime Email objects, EmailQueue entities, and FailedEmail entities.
 *
 * This factory centralizes the instantiation logic for email components,
 * ensuring consistency and proper dependency injection.
 */
class EmailFactory
{
    /**
     * Constructs a new EmailFactory instance.
     *
     * @param OrderRepositoryInterface $orderRepository The repository for managing Order entities.
     * @param UserRepositoryInterface $userRepository The repository for managing User entities.
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Creates a specific Email DTO based on the provided email template type and data.
     *
     * This method acts as a dispatcher, delegating to private methods to create
     * `OrderDTO` or `VerificationDTO` based on the `EmailTemplate` enum.
     *
     * @param EmailTemplate $type The type of email template.
     * @param array $data An associative array containing data required for the DTO.
     * @return DTO The created Email DTO (either OrderDTO or VerificationDTO).
     * @throws \InvalidArgumentException If the required entity (Order or User) is not found.
     */
    public function createDTO(EmailTemplate $type, array $data): DTO
    {
        return match($type) {
            EmailTemplate::ORDER_CONFIRMATION => $this->createOrderDTO($data),
            EmailTemplate::VERIFICATION => $this->createVerificationDTO($data),
        };
    }

    /**
     * Creates an OrderDTO from the provided data.
     *
     * Expects an 'order' key in the `$data` array, which can be an `Order` object
     * or an integer representing the Order ID. If an ID is provided, it attempts
     * to fetch the `Order` entity from the repository.
     *
     * @param array $data An associative array containing the 'order' key.
     * @return OrderDTO The created OrderDTO.
     * @throws \InvalidArgumentException If the order is not found when an ID is provided.
     */
    private function createOrderDTO(array $data): OrderDTO
    {
        $order = $data['order'];
        
        // If the order ID is passed instead of the object, retrieve the object
        if (is_numeric($order)) {
            $order = $this->orderRepository->find($order);
            if (!$order) {
                throw new \InvalidArgumentException('Order not found with ID: ' . $data['order']);
            }
        }
        
        return new OrderDTO($order);
    }

    /**
     * Creates a VerificationDTO from the provided data.
     *
     * Expects a 'user' key (User object or ID) and a 'confirmUrl' key in `$data`.
     * If a user ID is provided, it attempts to fetch the `User` entity.
     *
     * @param array $data An associative array containing 'user', 'confirmUrl', and optionally 'locale'.
     * @return VerificationDTO The created VerificationDTO.
     * @throws \InvalidArgumentException If the user is not found when an ID is provided.
     */
    private function createVerificationDTO(array $data): VerificationDTO
    {
        $user = $data['user'];
        
        // If the user ID is passed instead of the object, retrieve the object
        if (is_numeric($user)) {
            $user = $this->userRepository->find($user);
            if (!$user) {
                throw new \InvalidArgumentException('User not found with ID: ' . $data['user']);
            }
        }
        
        return new VerificationDTO(
            $user,
            $data['confirmUrl'],
            $data['locale'] ?? null, // Optional locale
        );
    }

    /**
     * Creates a Symfony Mime Email object.
     *
     * Constructs an `Email` object with sender, recipient, subject, HTML content,
     * and plain text content based on the provided email data.
     *
     * @param array $emailData An associative array with keys: 'sender_email', 'sender_name', 'subject', 'html_content', 'text_content'.
     * @param string $recipientEmail The email address of the recipient.
     * @return Email The created Symfony Mime Email object.
     */
    public function createEmail(array $emailData, string $recipientEmail): Email
    {
        return (new Email())
            ->from(new Address($emailData['sender_email'], $emailData['sender_name']))
            ->to($recipientEmail)
            ->subject($emailData['subject'])
            ->html($emailData['html_content'])
            ->text($emailData['text_content']);
    }

    /**
     * Creates an EmailQueue entity.
     *
     * Initializes a new `EmailQueue` entity with the specified email type,
     * context data, and locale.
     *
     * @param string $emailType The type of email to queue.
     * @param array $context The context data for the email.
     * @param string|null $locale The locale for the email.
     * @return EmailQueue The created EmailQueue entity.
     */
    public function createEmailQueue(
        string $emailType,
        array $context,
        ?string $locale, 
    ): EmailQueue {
        $email = new EmailQueue();
        $email->setEmailType($emailType)
            ->setContext($context)
            ->setLocale($locale)
        ;
        
        return $email;
    }

    /**
     * Creates a FailedEmail entity.
     *
     * Initializes a new `FailedEmail` entity with details about a failed email
     * sending attempt, including the error message, attempt count, and creation timestamp.
     *
     * @param string $emailType The type of email that failed.
     * @param array $context The context data of the failed email.
     * @param string|null $error The error message from the failed attempt.
     * @param int $attempts The number of attempts made to send this email.
     * @param DateTimeImmutable $createdAt The original creation timestamp of the email.
     * @return FailedEmail The created FailedEmail entity.
     */
    public function createFailedEmail(
        string $emailType,
        array $context,
        ?string $error,
        int $attempts,
        DateTimeImmutable $createdAt,
    ): FailedEmail {
        $email = new FailedEmail(); // Using new FailedEmail() directly
        $email->setEmailType($emailType)
            ->setContext($context)
            ->setError($error)
            ->setAttempts($attempts)
            ->setCreatedAt($createdAt)
        ;
        
        return $email;
    }
}