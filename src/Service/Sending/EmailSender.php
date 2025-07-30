<?php

declare(strict_types=1);

namespace App\Service\Sending;

use App\DTO\DTO;
use App\DTO\AbstractEmailDTO;
use App\Factory\EmailFactory; // Assuming this is needed for the parent's constructor
use App\Service\Rendering\EmailRenderer; // Assuming this is needed for the parent's constructor
use Symfony\Component\Mailer\MailerInterface; // Assuming this is needed for the parent's constructor
use Psr\Log\LoggerInterface; // Assuming this is needed for the parent's constructor (though not explicitly in Sender's constructor in the last provided version, it's common)

/**
 * Service for sending emails.
 *
 * This class extends the `Sender` abstract class and provides the concrete
 * implementation for dispatching emails. It uses an `AbstractEmailDTO` to
 * determine the recipient and content, renders the email, creates the
 * mailer message, and sends it via Symfony's Mailer component.
 */
class EmailSender extends Sender
{
    public function __construct(
        private readonly LoggerInterface $logger
    )
    {
        
    }
    /**
     * Sends an email based on the provided Data Transfer Object.
     *
     * This method first validates that the given DTO is an instance of `AbstractEmailDTO`.
     * It then retrieves the recipient's email address from the DTO.
     * The email content (HTML, plain text, subject, sender details) is rendered
     * using the `EmailRenderer`. An email message object is then created via the
     * `EmailFactory`, and finally, the email is dispatched using the `MailerInterface`.
     *
     * @param DTO $dto The Data Transfer Object containing all necessary information for sending the email.
     * Must be an instance of `App\DTO\AbstractEmailDTO`.
     * @return void
     * @throws \InvalidArgumentException If the provided DTO is not an instance of `AbstractEmailDTO`.
     * @throws \Exception If an error occurs during the email rendering process or during dispatching by the mailer.
     */
    public function send(DTO $dto): void
    {
        if (!$dto instanceof AbstractEmailDTO) {
            // Log the error if the DTO is not of the expected type.
            // (Assuming a logger is available via parent or direct injection if needed)
            // $this->logger->error('Invalid DTO type provided to EmailSender::send', ['dto_class' => get_class($dto)]);
            throw new \InvalidArgumentException('DTO must be instance of AbstractEmailDTO for email sending');
        }

        $recipientEmail = $dto->getEmail();
        
        // Retrieve context from the DTO. The `getContext` method is defined in `AbstractEmailDTO`.
        $context = $dto->getContext();
        
        // Render the email's components (subject, HTML body, text body, sender information)
        $emailData = $this->renderer->renderFromDTO($dto);
        
        // Create the actual email message object (e.g., Symfony\Component\Mime\Email)
        $email = $this->emailFactory->createEmail($emailData, $recipientEmail);
            
        // Send the email using the injected mailer instance
        $this->mailer->send($email);

        // Logging for successful email sending
        // (Assuming a logger is available via parent or direct injection if needed)
        
        $this->logger->info(
            sprintf(
                'Email sent successfully to %s (Template: %s). Subject: "%s"',
                $recipientEmail,
                $dto->getEmailTemplate()->name,
                $emailData['subject'] ?? 'N/A'
            ),
            [
                'recipient' => $recipientEmail,
                'template' => $dto->getEmailTemplate()->name,
                'subject' => $emailData['subject'] ?? null,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            ]
        );
    }
}