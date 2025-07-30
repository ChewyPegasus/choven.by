<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\OrderForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Order;
use App\Enum\EmailTemplate;
use App\Factory\DTOFactory;
use Psr\Log\LoggerInterface;
use App\Factory\EmailFactory;
use App\Factory\OrderFactory;
use App\Repository\Interfaces\EmailQueueRepositoryInterface;
use App\Repository\Interfaces\OrderRepositoryInterface;
use App\Service\Messaging\Producer\Producer;
use App\Service\OrderService;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/order')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly string $orderTopic,
        private readonly EmailFactory $emailFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EmailQueueRepositoryInterface $emailQueueRepository,
    )
    {
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        Producer $producer,
        EmailFactory $emailFactory,
        DTOFactory $DTOFactory,
        OrderFactory $orderFactory,
    ): Response
    {
        $dto = $DTOFactory->createFromRequest($request);

        $currentUser = $this->getUser();
        if ($currentUser) {
            $dto->email = ($currentUser->getUserIdentifier());
        }

        $form = $this->createForm(OrderForm::class, $dto, [
            'is_authenticated' => $currentUser !== null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            [$order, $errors] = $orderFactory->createFromFormDTO($dto);

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                
                return $this->render('order/new.html.twig', [
                    'form' => $form,
                ]);
            }

            $order->setLocale($request->getLocale());

            if ($this->getUser()) {
                $order->setUser($this->getUser());
            }
            
            $this->orderRepository->save($order);

            try {
                $producer->produce(
                    $this->orderTopic,
                    $emailFactory->createDTO(
                        EmailTemplate::ORDER_CONFIRMATION,
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
                $emailQueue = $this->emailFactory->createEmailQueue(
                    EmailTemplate::ORDER_CONFIRMATION->value,
                    [
                        'order' => $order->getId()
                    ],
                    $request->getLocale(),
                );
                
                $this->emailQueueRepository->save($emailQueue);
                
                // Notifying user that email will be sent later
                $this->addFlash('info', $translator->trans('order.info.email_queued'));
            }

            return $this->redirectToRoute('app_main');
        }

        return $this->render('order/new.html.twig', [
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
