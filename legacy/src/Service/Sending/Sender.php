<?php

declare(strict_types=1);

namespace App\Service\Sending;

use App\DTO\DTO;
use App\Factory\EmailFactory;
use App\Service\Rendering\EmailRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Abstract base class for sender services.
 *
 * This class provides common dependencies required for sending various types of communications,
 * such as emails. Concrete implementations will define the specific sending logic
 * for different DTO types.
 */
abstract class Sender
{
    /**
     * Constructs a new Sender instance.
     *
     * @param MailerInterface $mailer The Symfony Mailer instance used for dispatching messages.
     * @param EmailRenderer $renderer The service responsible for rendering content into appropriate formats (e.g., HTML, plain text).
     * @param string $senderEmail The default "from" email address for messages sent by this sender.
     * @param string $adminEmail The email address designated for administrative communications or notifications.
     * @param EmailFactory $emailFactory The factory responsible for creating message objects (e.g., `Symfony\Component\Mime\Email`).
     */
    public function __construct(
        protected readonly MailerInterface $mailer,
        protected readonly EmailRenderer $renderer,
        protected readonly string $senderEmail,
        protected readonly string $adminEmail,
        protected readonly EmailFactory $emailFactory,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Abstract method for sending a DTO.
     *
     * Concrete implementations of this abstract class must provide their specific
     * logic for how to handle and send the provided Data Transfer Object.
     *
     * @param DTO $dto The Data Transfer Object containing the necessary data for sending.
     * @return void
     * @throws \Exception If an error occurs during the sending process.
     */
    abstract function send(DTO $dto): void;
}