<?php

namespace App\Controller;

use App\Entity\Order;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mime\Email;

final class MailerController extends AbstractController
{
    #[Route('/mailer', name: 'app_mailer')]
    public function index(MailerInterface $mailer, Order $order): Response
    {
        $email = (new Email())
            ->from('choven.by@gmail.com')
            ->to($order->getEmail())
            ->addBcc('shpoka82@mail.ru');
    }
}
