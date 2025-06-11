<?php

declare(strict_types=1);

namespace App\Service\Sending;

use App\DTO\DTO;
use App\Service\Rendering\EmailRenderer;
use Symfony\Component\Mailer\MailerInterface;

abstract class Sender
{
    public function __construct(
        protected MailerInterface $mailer,
        protected EmailRenderer $renderer,
        protected string $senderEmail,
        protected string $adminEmail,
    )
    {
    }

    abstract function send(DTO $dto): void;
}
