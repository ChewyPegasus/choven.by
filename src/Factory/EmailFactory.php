<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\DTO;
use App\DTO\OrderDTO;
use App\DTO\VerificationDTO;
use App\Enum\EmailType;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailFactory
{
    public function createDTO(EmailType $type, array $data): DTO
    {
        return match($type) {
            EmailType::ORDER_CONFIRMATION => new OrderDTO($data['order']),
            EmailType::VERIFICATION => new VerificationDTO(
                $data['user'],
                $data['confirmUrl'],
            ),
        };
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
