<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Form\Admin\OrderAdminType;
use App\Form\Admin\OrderFilterType;
use App\Model\OrderFilter;
use App\Repository\OrderRepository;
use App\Service\AvailabilityChecker;
use App\Service\BookingService;
use App\Service\SiteSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/orders')]
#[IsGranted('ROLE_ADMIN')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly BookingService $bookingService,
    ) {
    }

    #[Route('', name: 'app_admin_orders', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$filter, $filterForm] = $this->buildFilter($request);

        return $this->render('admin/order/index.html.twig', [
            'page' => $this->orders->findByFilter($filter, $request->query->getInt('page', 1)),
            'filter' => $filter,
            'filter_form' => $filterForm,
        ]);
    }

    #[Route('/export.csv', name: 'app_admin_orders_export', methods: ['GET'])]
    public function export(Request $request, TranslatorInterface $translator): StreamedResponse
    {
        [$filter] = $this->buildFilter($request);
        $orders = $this->orders;

        $response = new StreamedResponse(static function () use ($orders, $filter, $translator): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            // UTF-8 BOM so that Excel opens Cyrillic text correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Reference', 'Status', 'Start', 'End', 'Days', 'People', 'River', 'Package', 'Tour', 'Extras', 'Name', 'Phone', 'Email', 'Price BYN', 'Discount BYN', 'Promo code', 'Gift BYN', 'Paid BYN', 'Comment', 'Admin note', 'Created'], ';', '"', '');

            foreach ($orders->iterateByFilter($filter) as $order) {
                fputcsv($out, array_map(self::csvCell(...), [
                    $order->getReference(),
                    $order->getStatus()->trans($translator),
                    $order->getStartDate()?->format('Y-m-d'),
                    $order->getEndDate()?->format('Y-m-d'),
                    $order->getDurationDays(),
                    $order->getAmountOfPeople(),
                    $order->getRiver()?->trans($translator),
                    $order->getPackage()?->trans($translator),
                    $order->getTour()?->getLocalizedTitle(),
                    implode(', ', array_map(
                        static fn ($e): string => $e->getLocalizedName().($e->getUnit()->hasQuantity() ? ' ×'.$e->getQuantity() : ''),
                        $order->getExtras()->toArray(),
                    )),
                    $order->getName(),
                    $order->getPhoneE164(),
                    $order->getEmail(),
                    $order->getEstimatedPrice(),
                    $order->getDiscount() ?: null,
                    $order->getPromoCode()?->getCode(),
                    $order->getGiftAmount() ?: null,
                    $order->getPaidAmount() ?: null,
                    $order->getDescription(),
                    $order->getAdminNote(),
                    $order->getCreatedAt()->format('Y-m-d H:i'),
                ]), ';', '"', '');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'orders-'.date('Y-m-d').'.csv',
        ));

        return $response;
    }

    /**
     * Month grid with the number of people booked per day.
     */
    #[Route('/calendar', name: 'app_admin_calendar', methods: ['GET'])]
    public function calendar(Request $request, SiteSettings $settings): Response
    {
        $month = \DateTimeImmutable::createFromFormat('!Y-m', $request->query->getString('month'))
            ?: new \DateTimeImmutable('first day of this month');
        $month = $month->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $month->modify('last day of this month');

        // Calendar starts on Monday of the first week and ends on Sunday of the last one.
        $gridStart = $month->modify('-'.((int) $month->format('N') - 1).' days');
        $gridEnd = $monthEnd->modify('+'.(7 - (int) $monthEnd->format('N')).' days');

        $ordersByDay = [];
        foreach ($this->orders->findActiveInPeriod($gridStart, $gridEnd, statuses: [OrderStatus::NEW, OrderStatus::CONFIRMED, OrderStatus::COMPLETED]) as $order) {
            for ($day = $order->getStartDate(); null !== $day && $day <= $order->getEndDate(); $day = $day->modify('+1 day')) {
                $ordersByDay[$day->format('Y-m-d')][] = $order;
            }
        }

        $weeks = [];
        for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $dayOrders = $ordersByDay[$key] ?? [];
            $weeks[(int) floor($gridStart->diff($day)->days / 7)][] = [
                'date' => $day,
                'in_month' => $day->format('m') === $month->format('m'),
                'orders' => $dayOrders,
                'people' => array_sum(array_map(static fn (Order $o): int => (int) $o->getAmountOfPeople(), $dayOrders)),
                'blocked' => $settings->isDateBlocked($day),
            ];
        }

        return $this->render('admin/order/calendar.html.twig', [
            'month' => $month,
            'weeks' => $weeks,
            'capacity' => $settings->getDailyCapacity(),
        ]);
    }

    /**
     * Printable list of everybody on the water on a given day (for instructors and drivers).
     */
    #[Route('/day/{date}', name: 'app_admin_order_day', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET'])]
    public function day(string $date, SiteSettings $settings): Response
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $day) {
            throw $this->createNotFoundException();
        }

        $orders = $this->orders->findActiveInPeriod($day, $day);
        usort($orders, static fn (Order $a, Order $b): int => [$a->getRiver()?->value, $a->getStartDate()] <=> [$b->getRiver()?->value, $b->getStartDate()]);

        return $this->render('admin/order/day.html.twig', [
            'day' => $day,
            'orders' => $orders,
            'people' => array_sum(array_map(static fn (Order $o): int => (int) $o->getAmountOfPeople(), $orders)),
            'capacity' => $settings->getDailyCapacity(),
        ]);
    }

    #[Route('/new', name: 'app_admin_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $order = new Order();
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('date'));
        if (false !== $date) {
            $order->setStartDate($date);
        }

        $form = $this->createForm(OrderAdminType::class, $order, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->bookingService->createByAdmin($order, (bool) $form->get('notifyCustomer')->getData());
            $this->addFlash('success', 'admin.orders.flash.created');

            return $this->redirectToRoute('app_admin_order_edit', ['id' => $order->getId()]);
        }

        return $this->render('admin/order/form.html.twig', ['form' => $form, 'order' => $order]);
    }

    #[Route('/{id}/edit', name: 'app_admin_order_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Order $order, Request $request, AvailabilityChecker $checker): Response
    {
        $previousStatus = $order->getStatus();
        $previous = ['start' => $order->getStartDate(), 'end' => $order->getEndDate(), 'people' => $order->getAmountOfPeople()];
        $form = $this->createForm(OrderAdminType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->bookingService->update($order, $previousStatus, (bool) $form->get('notifyCustomer')->getData(), $previous);
            $this->addFlash('success', 'admin.orders.flash.saved');

            return $this->redirectToRoute('app_admin_order_edit', ['id' => $order->getId()]);
        }

        $conflicts = null !== $order->getStartDate() && $order->getStatus()->isActive()
            ? $checker->check($order->getStartDate(), (int) $order->getDurationDays(), (int) $order->getAmountOfPeople(), $order)
            : [];

        return $this->render('admin/order/form.html.twig', [
            'form' => $form,
            'order' => $order,
            'conflicts' => $conflicts,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Order $order, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete-order-'.$order->getId(), $request->getPayload()->getString('_token'))) {
            $this->bookingService->delete($order);
            $this->addFlash('success', 'admin.orders.flash.deleted');
        } else {
            $this->addFlash('error', 'flash.invalid_csrf');
        }

        return $this->redirectToRoute('app_admin_orders');
    }

    /**
     * Neutralizes values that a spreadsheet would execute as a formula (=, +, -, @ …).
     */
    public static function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return '' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @return array{0: OrderFilter, 1: \Symfony\Component\Form\FormInterface<OrderFilter>}
     */
    private function buildFilter(Request $request): array
    {
        $filter = new OrderFilter();
        $form = $this->createForm(OrderFilterType::class, $filter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $filter = new OrderFilter();
        }

        return [$filter, $form];
    }
}
