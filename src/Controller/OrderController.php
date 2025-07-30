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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for handling order-related operations.
 *
 * This controller manages the creation of new orders and the display of individual order details.
 * It integrates with various services for form handling, data persistence, email queuing,
 * and message production to Kafka.
 */
#[Route('/order')]
final class OrderController extends AbstractController
{
    /**
     * @param string $orderTopic The Kafka topic name for order messages.
     * @param EmailFactory $emailFactory The factory for creating email-related objects.
     * @param OrderRepositoryInterface $orderRepository The repository for managing Order entities.
     * @param EmailQueueRepositoryInterface $emailQueueRepository The repository for managing email queue entities.
     */
    public function __construct(
        private readonly string $orderTopic,
        private readonly EmailFactory $emailFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EmailQueueRepositoryInterface $emailQueueRepository,
    ) {
    }

    /**
     * Handles the creation of a new order.
     *
     * Displays a form for new order submission. Upon valid submission, it creates
     * an Order entity, saves it, and attempts to publish an order confirmation
     * email message to Kafka. If Kafka publishing fails, the email is queued for retry.
     *
     * @param Request $request The current HTTP request.
     * @param LoggerInterface $logger The logger instance for recording activities and errors.
     * @param TranslatorInterface $translator The translator service for internationalization.
     * @param Producer $producer The Kafka producer service.
     * @param EmailFactory $emailFactory The factory for creating email-related objects.
     * @param DTOFactory $DTOFactory The factory for creating DTOs from requests.
     * @param OrderFactory $orderFactory The factory for creating Order entities from DTOs.
     * @return Response The HTTP response for the new order form or a redirect.
     */
    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        Producer $producer,
        EmailFactory $emailFactory,
        DTOFactory $DTOFactory,
        OrderFactory $orderFactory,
    ): Response {
        $dto = $DTOFactory->createFromRequest($request);

        $currentUser = $this->getUser();
        if ($currentUser) {
            // Pre-fill email if user is authenticated
            $dto->email = ($currentUser->getUserIdentifier());
        }

        $form = $this->createForm(OrderForm::class, $dto, [
            'is_authenticated' => $currentUser !== null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Create Order entity from DTO, handling potential validation errors
            [$order, $errors] = $orderFactory->createFromFormDTO($dto);

            if (count($errors) > 0) {
                // Add flash messages for form validation errors
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                
                return $this->render('order/new.html.twig', [
                    'form' => $form,
                ]);
            }

            // Set locale and associate user if authenticated
            $order->setLocale($request->getLocale());
            if ($this->getUser()) {
                $order->setUser($this->getUser());
            }
            
            $this->orderRepository->save($order); // Persist the order

            try {
                // Attempt to publish order confirmation message to Kafka
                $producer->produce(
                    $this->orderTopic,
                    $emailFactory->createDTO(
                        EmailTemplate::ORDER_CONFIRMATION,
                        [
                            'order' => $order,
                        ]
                    ),
                    'order_' . $order->getId(), // Message key
                );

                $this->addFlash('success', $translator->trans('order.success.confirmation_sent'));
            } catch (\Exception $e) {
                $logger->error('Kafka publishing failed: ' . $e->getMessage(), ['exception' => $e]);

                // If Kafka fails, save email to a local queue for later retry
                $emailQueue = $this->emailFactory->createEmailQueue(
                    EmailTemplate::ORDER_CONFIRMATION->value,
                    [
                        'order' => $order->getId() // Store order ID, not the full object
                    ],
                    $request->getLocale(),
                );
                
                $this->emailQueueRepository->save($emailQueue);
                
                // Notify user that email will be sent later
                $this->addFlash('info', $translator->trans('order.info.email_queued'));
            }

            return $this->redirectToRoute('app_main'); // Redirect after successful submission
        }

        // Render the form for initial display or if validation fails
        return $this->render('order/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Displays the details of a single order.
     *
     * @param Order $order The Order entity resolved by the route parameter.
     * @return Response The HTTP response for the order details page.
     */
    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }
}