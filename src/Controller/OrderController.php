<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\OrderForm;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Order;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;
use App\Enum\River;
use App\Enum\Type;

#[Route('/order')]
final class OrderController extends AbstractController
{
    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        LoggerInterface $logger
    ): Response
    {
        $order = new Order();

        $type = $request->query->get('type');
        $duration = $request->query->get('duration');
        $river = $request->query->get('river');

        if ($type) {
            $order->setType(Type::from($type));
        }
        if ($river) {
            $order->setRiver(River::from($river));
        }
        if ($duration) {
            $order->setDuration(new \DateInterval('P' . $duration . 'D'));
        }

        $form = $this->createForm(OrderForm::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $durationDays = $form->get('durationDays')->getData();
            $order->setDurationDays($durationDays);
            
            $entityManager->persist($order);
            $entityManager->flush();

            try {
                $emailService->sendOrderConfirmation($order);
                $this->addFlash('success', 'Ваш заказ успешно отправлен. На ваш email отправлено подтверждение.');
            } catch (\Exception $e) {
                $logger->error('Email sending failed: ' . $e->getMessage(), ['exception' => $e]);
                $this->addFlash('success', 'Ваш заказ успешно отправлен.');
                $this->addFlash('warning', 'Возникли проблемы при отправке письма с подтверждением. Мы свяжемся с вами в ближайшее время.');
            }

            return $this->redirectToRoute('app_main');
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }
}
