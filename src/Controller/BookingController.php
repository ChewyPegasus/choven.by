<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Extra;
use App\Entity\Order;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\WaitlistEntry;
use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;
use App\Exception\DatesUnavailableException;
use App\Form\BookingType;
use App\Form\RescheduleType;
use App\Form\ReviewType;
use App\Form\WaitlistType;
use App\Messenger\SubscriptionLinker;
use App\Messenger\TelegramBot;
use App\Messenger\ViberBot;
use App\Payment\PaymentException;
use App\Repository\ExtraRepository;
use App\Repository\MessengerSubscriptionRepository;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Repository\TourRepository;
use App\Service\AvailabilityChecker;
use App\Service\BookingPolicy;
use App\Service\BookingService;
use App\Service\DiscountResolver;
use App\Service\IcsGenerator;
use App\Service\PaymentService;
use App\Service\SiteSettings;
use App\Service\TeamNotifier;
use App\Service\WaitlistService;
use App\Service\WeatherService;
use App\Validator\AvailableDates;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/booking')]
final class BookingController extends AbstractController
{
    private const TOKEN = '[a-f0-9]{64}';

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly BookingService $bookingService,
    ) {
    }

    #[Route('', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        TourRepository $tours,
        ExtraRepository $extraRepository,
        SiteSettings $settings,
        DiscountResolver $discounts,
        PaymentService $payments,
        RateLimiterFactoryInterface $bookingLimiter,
        TranslatorInterface $translator,
    ): Response {
        if (!$settings->isBookingEnabled()) {
            return $this->render('booking/closed.html.twig');
        }

        $order = $this->prefill($request, $tours);
        $user = $this->getUser();
        $extras = $extraRepository->findActive();
        $form = $this->createForm(BookingType::class, $order, ['email_locked' => $user instanceof User, 'extras' => $extras]);
        $form->handleRequest($request);

        $soldOut = false;
        if ($form->isSubmitted()) {
            $discount = $discounts->resolve($form->get('promoCode')->getData());
            if (null !== $discount['error']) {
                $form->get('promoCode')->addError(new FormError($translator->trans($discount['error'], [], 'validators')));
            }

            if ($form->isValid()) {
                if (!$bookingLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                    $this->addFlash('error', 'flash.too_many_requests');

                    return $this->redirectToRoute('app_order_new');
                }

                try {
                    $this->bookingService->place(
                        $order,
                        $user instanceof User ? $user : null,
                        $request->getLocale(),
                        $this->extraQuantities($form, $extras),
                        $discount['promo'],
                        $discount['gift'],
                    );

                    return $this->redirectToRoute('app_order_show', ['token' => $order->getAccessToken(), 'created' => 1]);
                } catch (DatesUnavailableException) {
                    $form->get('startDate')->addError(new FormError($translator->trans('order.error.just_taken', [], 'validators')));
                    $soldOut = true;
                }
            }

            $soldOut = $soldOut || $this->hasAvailabilityError($form);
        }

        $waitlistForm = $soldOut
            ? $this->createForm(WaitlistType::class, WaitlistEntry::fromOrder($order), [
                'action' => $this->generateUrl('app_waitlist_join'),
                'tour_slug' => $order->getTour()?->getSlug(),
            ])
            : null;

        return $this->render('booking/new.html.twig', [
            'form' => $form,
            'order' => $order,
            'tour' => $order->getTour(),
            'extras' => $extras,
            'waitlist_form' => $waitlistForm,
            'payments_enabled' => $payments->isEnabled(),
        ]);
    }

    /**
     * Checks a promo code or gift certificate for the live price preview of the booking form.
     */
    #[Route('/promo', name: 'app_booking_promo', methods: ['GET'])]
    public function promo(Request $request, DiscountResolver $discounts, RateLimiterFactoryInterface $promoLimiter, TranslatorInterface $translator): JsonResponse
    {
        if (!$promoLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonResponse(['valid' => false, 'message' => $translator->trans('flash.too_many_requests')], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $result = $discounts->resolve($request->query->getString('code'));
        $response = match (true) {
            null !== $result['promo'] => ['valid' => true, 'kind' => 'promo', 'type' => $result['promo']->getType()->value, 'value' => $result['promo']->getValue()],
            null !== $result['gift'] => ['valid' => true, 'kind' => 'gift', 'balance' => $result['gift']->getBalance()],
            null !== $result['error'] => ['valid' => false, 'message' => $translator->trans($result['error'], [], 'validators')],
            default => ['valid' => false],
        };

        $json = new JsonResponse($response);
        $json->setPrivate();
        $json->headers->set('X-Robots-Tag', 'noindex');

        return $json;
    }

    #[Route('/waitlist', name: 'app_waitlist_join', methods: ['POST'])]
    public function joinWaitlist(Request $request, TourRepository $tours, WaitlistService $waitlist, RateLimiterFactoryInterface $bookingLimiter): Response
    {
        $entry = new WaitlistEntry();
        $form = $this->createForm(WaitlistType::class, $entry, ['action' => $this->generateUrl('app_waitlist_join')]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('booking/waitlist.html.twig', ['form' => $form], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        if (!$bookingLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $this->addFlash('error', 'flash.too_many_requests');

            return $this->redirectToRoute('app_order_new');
        }

        $slug = (string) $form->get('tourSlug')->getData();
        $entry->setTour('' !== $slug ? $tours->findOneActiveBySlug($slug) : null)->setLocale($request->getLocale());
        $joined = $waitlist->join($entry);

        return $this->render('booking/waitlist.html.twig', ['form' => null, 'entry' => $entry, 'already' => !$joined]);
    }

    /**
     * Fully booked / blocked days for the date picker.
     */
    #[Route('/availability', name: 'app_booking_availability', methods: ['GET'])]
    public function availability(Request $request, AvailabilityChecker $checker): JsonResponse
    {
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('from')) ?: new \DateTimeImmutable('today');
        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('to')) ?: $from->modify('+60 days');
        if ($to < $from || $from->diff($to)->days > 400) {
            $to = $from->modify('+60 days');
        }

        $response = new JsonResponse($checker->getCalendar($from, $to));
        $response->setPrivate()->setMaxAge(60);

        return $response;
    }

    #[Route('/{token}', name: 'app_order_show', requirements: ['token' => self::TOKEN], methods: ['GET'])]
    public function show(
        string $token,
        Request $request,
        ReviewRepository $reviews,
        BookingPolicy $policy,
        PaymentService $payments,
        WeatherService $weather,
        SubscriptionLinker $linker,
        MessengerSubscriptionRepository $subscriptions,
        TelegramBot $telegram,
        ViberBot $viber,
    ): Response {
        $order = $this->findOrder($token);
        $payload = $linker->payloadForOrder($order);

        $response = $this->render('booking/show.html.twig', [
            'order' => $order,
            'just_created' => $request->query->getBoolean('created'),
            'review' => $reviews->findOneByOrder($order),
            'policy' => $policy,
            'can_pay' => $payments->isEnabled() && $order->getDepositDue() > 0 && $order->getStatus()->isActive() && $order->isUpcoming(),
            'forecast' => $order->getStatus()->isActive() ? $weather->forOrder($order) : [],
            'messengers' => [
                'telegram' => $telegram->canLinkCustomers() ? $telegram->getDeepLink($payload) : null,
                'viber' => $viber->isEnabled() ? $viber->getDeepLink($payload) : null,
                'connected' => array_map(static fn ($s) => $s->getChannel()->getLabel(), $subscriptions->findForOrder($order)),
            ],
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();

        return $response;
    }

    #[Route('/{token}/pay', name: 'app_order_pay', requirements: ['token' => self::TOKEN], methods: ['POST'])]
    public function pay(string $token, Request $request, PaymentService $payments): Response
    {
        $order = $this->findOrder($token);

        if (!$this->isCsrfTokenValid('pay-order-'.$order->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute('app_order_show', ['token' => $token]);
        }

        try {
            return $this->redirect($payments->startForOrder($order));
        } catch (PaymentException) {
            $this->addFlash('error', 'payment.flash.unavailable');

            return $this->redirectToRoute('app_order_show', ['token' => $token]);
        }
    }

    #[Route('/{token}/cancel', name: 'app_order_cancel', requirements: ['token' => self::TOKEN], methods: ['POST'])]
    public function cancel(string $token, Request $request): Response
    {
        $order = $this->findOrder($token);

        if (!$this->isCsrfTokenValid('cancel-order-'.$order->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
        } elseif (!$order->canBeCancelledByCustomer()) {
            $this->addFlash('error', 'booking.flash.cannot_cancel');
        } else {
            $this->bookingService->cancelByCustomer($order);
            $this->addFlash('success', 'booking.flash.cancelled');
        }

        return $this->redirectToRoute('app_order_show', ['token' => $token]);
    }

    #[Route('/{token}/reschedule', name: 'app_order_reschedule', requirements: ['token' => self::TOKEN], methods: ['GET', 'POST'])]
    public function reschedule(string $token, Request $request, BookingPolicy $policy, TranslatorInterface $translator): Response
    {
        $order = $this->findOrder($token);
        if (!$policy->canReschedule($order)) {
            $this->addFlash('error', 'reschedule.flash.not_allowed');

            return $this->redirectToRoute('app_order_show', ['token' => $token]);
        }

        $form = $this->createForm(RescheduleType::class, ['startDate' => $order->getStartDate()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \DateTimeImmutable $date */
            $date = $form->get('startDate')->getData();

            if ($date == $order->getStartDate()) {
                $form->get('startDate')->addError(new FormError($translator->trans('reschedule.same_date', [], 'validators')));
            } else {
                try {
                    $this->bookingService->reschedule($order, $date);
                    $this->addFlash('success', 'reschedule.flash.done');

                    return $this->redirectToRoute('app_order_show', ['token' => $token]);
                } catch (DatesUnavailableException) {
                    $form->get('startDate')->addError(new FormError($translator->trans('reschedule.unavailable', [], 'validators')));
                }
            }
        }

        $response = $this->render('booking/reschedule.html.twig', ['form' => $form, 'order' => $order, 'policy' => $policy]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    #[Route('/{token}/calendar.ics', name: 'app_order_ics', requirements: ['token' => self::TOKEN], methods: ['GET'])]
    public function calendar(string $token, IcsGenerator $ics): Response
    {
        $order = $this->findOrder($token);
        $url = $this->generateUrl('app_order_show', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = new Response($ics->forOrder($order, $url));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $order->getReference().'.ics',
        ));

        return $response;
    }

    #[Route('/{token}/review', name: 'app_review_new', requirements: ['token' => self::TOKEN], methods: ['GET', 'POST'])]
    public function review(string $token, Request $request, ReviewRepository $reviews, TeamNotifier $teamNotifier): Response
    {
        $order = $this->findOrder($token);

        if (null !== $reviews->findOneByOrder($order)) {
            $this->addFlash('info', 'review.flash.already_sent');

            return $this->redirectToRoute('app_order_show', ['token' => $token]);
        }

        $tookPlace = OrderStatus::COMPLETED === $order->getStatus()
            || (OrderStatus::CONFIRMED === $order->getStatus() && !$order->isUpcoming());
        if (!$tookPlace) {
            $this->addFlash('info', 'review.flash.after_trip');

            return $this->redirectToRoute('app_order_show', ['token' => $token]);
        }

        $review = (new Review())
            ->setOrder($order)
            ->setAuthor($order->getUser())
            ->setAuthorName($order->getName())
            ->setLocale($request->getLocale());

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reviews->save($review);
            } catch (UniqueConstraintViolationException) {
                // Double submit: the first one won.
                return $this->redirectToRoute('app_order_show', ['token' => $token]);
            }
            $teamNotifier->newReview($review);
            $this->addFlash('success', 'review.flash.thanks');

            return $this->redirectToRoute('app_order_show', ['token' => $token]);
        }

        return $this->render('booking/review.html.twig', ['form' => $form, 'order' => $order]);
    }

    private function findOrder(string $token): Order
    {
        return $this->orders->findOneByAccessToken($token) ?? throw $this->createNotFoundException();
    }

    /**
     * @param FormInterface<Order> $form
     * @param list<Extra>          $extras
     *
     * @return array<int, int> extra id => quantity
     */
    private function extraQuantities(FormInterface $form, array $extras): array
    {
        if (!$form->has('extras')) {
            return [];
        }

        $quantities = [];
        foreach ($extras as $extra) {
            $name = 'e'.$extra->getId();
            if (!$form->get('extras')->has($name)) {
                continue;
            }
            $value = $form->get('extras')->get($name)->getData();
            $quantity = true === $value ? 1 : (\is_int($value) ? $value : (int) $value);
            if ($quantity > 0) {
                $quantities[(int) $extra->getId()] = $quantity;
            }
        }

        return $quantities;
    }

    /**
     * @param FormInterface<Order> $form
     */
    private function hasAvailabilityError(FormInterface $form): bool
    {
        foreach ($form->get('startDate')->getErrors() as $error) {
            $cause = $error->getCause();
            if ($cause instanceof ConstraintViolationInterface && $cause->getConstraint() instanceof AvailableDates) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pre-fills the form from the query string (?tour=…&river=…&package=…&duration=…&date=…&people=…)
     * and from the signed-in user's profile.
     */
    private function prefill(Request $request, TourRepository $tours): Order
    {
        $order = new Order();
        $query = $request->query;

        $tourSlug = $query->getString('tour');
        $tour = '' !== $tourSlug ? $tours->findOneActiveBySlug($tourSlug) : null;
        if (null !== $tour) {
            $order->setTour($tour)
                ->setPackage($tour->getPackage())
                ->setRiver($tour->getRiver())
                ->setDurationDays($tour->getDurationDays() ?? 1);
        }

        $order->setRiver(River::tryFrom($query->getString('river')) ?? $order->getRiver());
        $order->setPackage(Package::tryFrom($query->getString('package', $query->getString('type'))) ?? $order->getPackage());

        $duration = $query->getInt('duration');
        if ($duration >= Order::MIN_DAYS && $duration <= Order::MAX_DAYS) {
            $order->setDurationDays($duration);
        }
        $people = $query->getInt('people');
        if ($people >= Order::MIN_PEOPLE && $people <= Order::MAX_PEOPLE) {
            $order->setAmountOfPeople($people);
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->getString('date'));
        if (false !== $date && $date >= new \DateTimeImmutable('today')) {
            $order->setStartDate($date);
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $order->setEmail($user->getEmail())
                ->setName($user->getName())
                ->setPhone($user->getPhone());
        }

        return $order;
    }
}
