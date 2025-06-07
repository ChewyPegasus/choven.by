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
use App\Enum\DTOType;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use App\Enum\River;
use App\Enum\Type;
use App\Factory\DTOFactory;
use App\Service\OrderService;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/order')]
final class OrderController extends AbstractController
{
    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        EmailSender $sender,
        LoggerInterface $logger,
        DTOFactory $dtoFactory,
        TranslatorInterface $translator,
        OrderService $orderService
    ): Response
    {
        $order = $orderService->create($request);

        $form = $this->createForm(OrderForm::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $durationDays = $form->get('durationDays')->getData();
            $order->setDurationDays($durationDays);
            
            $entityManager->persist($order);
            $entityManager->flush();

            try {
                $sender->send($dtoFactory->create(
                    DTOType::ORDER_CONFIRMATION,
                    [
                        'order' => $order,
                    ],
                ));
                $this->addFlash('success', $translator->trans('order.success.confirmation_sent'));
            } catch (\Exception $e) {
                $logger->error('Email sending failed: ' . $e->getMessage(), ['exception' => $e]);
                $this->addFlash('success', $translator->trans('order.success.order_created'));
                $this->addFlash('warning', $translator->trans('order.warning.email_failed'));
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
