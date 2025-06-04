<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use App\Entity\Order;

class EmailService {
    public function __construct(
        private MailerInterface $mailer,
        private string $senderEmail,
        private string $adminEmail,
    )
    {
    }

    public function sendOrderConfirmation(Order $order): void 
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Choven.by'))
            ->to(new Address($order->getEmail()))
            ->bcc($this->adminEmail)
            ->subject('Подтверждение бронирования сплава')
            ->htmlTemplate('mailer/index.html.twig')
            ->textTemplate('mailer/index.txt.twig')
            ->context([
                'order' => $order,
            ]);
        $this->mailer->send($email);
    }
}