<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailQueue;
use App\Form\OrderForm;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Order;
use App\Enum\EmailType;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use App\Factory\EmailFactory;
use App\Service\Messaging\Producer\Producer;
use App\Service\OrderService;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/order')]
final class OrderController extends AbstractController
{
    public function __construct(private string $orderTopic)
    {
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        OrderService $orderService,
        Producer $producer,
        EmailFactory $emailFactory,
    ): Response
    {
        $order = $orderService->create($request);
        $form = $this->createForm(OrderForm::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $durationDays = $form->get('durationDays')->getData();
            $order->setDurationDays($durationDays);
            $order->setLocale($request->getLocale());
            
            $entityManager->persist($order);
            $entityManager->flush();

            try {
                $producer->produce(
                    $this->orderTopic,
                    $emailFactory->createDTO(
                        EmailType::ORDER_CONFIRMATION,
                        [
                            'order' => $order,
                        ]
                    ),
                    'order_' . $order->getId(),
                );

                $this->addFlash('success', $translator->trans('order.success.confirmation_sent'));
            } catch (\Exception $e) {
                $logger->error('Kafka publishing failed: ' . $e->getMessage(), ['exception' => $e]);

                // Saving to email queue for retry
                $emailQueue = new EmailQueue();
                $emailQueue->setEmailType(EmailType::ORDER_CONFIRMATION->value)
                        ->setContext(['order' => $order->getId()])
                        ->setLocale($request->getLocale());
                
                $entityManager->persist($emailQueue);
                $entityManager->flush();
                
                // Notifying user that email will be sent later
                $this->addFlash('info', $translator->trans('order.info.email_queued'));
            }

            return $this->redirectToRoute('app_main');
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    private function sendConfirmationEmail(Order $order, EmailSender $sender, EmailFactory $emailFactory, LoggerInterface $logger, TranslatorInterface $translator): void
    {
        try {
            $sender->send($emailFactory->createDTO(
                EmailType::ORDER_CONFIRMATION,
                ['order' => $order]
            ));
        } catch (\Exception $emailException) {
            $logger->error('Email sending failed: ' . $emailException->getMessage(), ['exception' => $emailException]);
            $this->addFlash('warning', $translator->trans('order.warning.email_failed'));
        }
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }
}
