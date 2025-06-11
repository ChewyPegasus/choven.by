<?php

declare(strict_types=1);

namespace App\Service\Sending;

use App\DTO\DTO;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use App\DTO\AbstractEmailDTO;
use App\Service\Rendering\EmailRenderer;

class EmailSender extends Sender
{
    public function send(DTO $dto): void
    {
        if (!$dto instanceof AbstractEmailDTO) {
            throw new \InvalidArgumentException('DTO must be instance of AbstractEmailDTO for email sending');
        }

        $recipientEmail = $dto->getEmail();
        
        $context = method_exists($dto, 'getContext') 
            ? $dto->getContext() 
            : [];
        
        $emailData = $this->renderer->renderFromDTO($dto);
        
        $email = (new Email())
            ->from(new Address($emailData['sender_email'], $emailData['sender_name']))
            ->to($recipientEmail)
            ->subject($emailData['subject'])
            ->html($emailData['html_content'])
            ->text($emailData['text_content']);
            
        $this->mailer->send($email);
    }
}
