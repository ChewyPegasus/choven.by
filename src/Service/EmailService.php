<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\DTO;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use App\DTO\AbstractEmailDTO;

class EmailService implements Sender
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailRenderer $renderer,
        private string $senderEmail,
        private string $adminEmail,
    )
    {
    }

    public function send(DTO $dto): void
    {
        if (!$dto instanceof AbstractEmailDTO) {
            throw new \InvalidArgumentException('DTO must be instance of AbstractEmailDTO for email sending');
        }

        $entity = $dto->getEntity();
        
        $recipientEmail = method_exists($entity, 'getEmail') 
            ? $entity->getEmail() 
            : $this->adminEmail;
        

        $template = $dto->getEmailTemplate();
        $additionalContext = $dto->getAdditionalContext();
        
        $emailData = $this->renderer->render($template, $entity, $additionalContext);
        
        $email = (new Email())
            ->from(new Address($emailData['sender_email'], $emailData['sender_name']))
            ->to($recipientEmail)
            ->subject($emailData['subject'])
            ->html($emailData['html_content'])
            ->text($emailData['text_content']);
            
        $this->mailer->send($email);
    }
}