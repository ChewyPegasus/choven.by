<?php

declare(strict_types=1);

namespace App\Service\Sending;

use App\DTO\DTO;
use App\DTO\AbstractEmailDTO;

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
        
        $email = $this->emailFactory->createEmail($emailData, $recipientEmail);
            
        $this->mailer->send($email);
    }
}
