<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Review;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;
use App\Form\BookingType;
use App\Form\ReviewType;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Repository\TourRepository;
use App\Service\AvailabilityChecker;
use App\Service\BookingService;
use App\Service\IcsGenerator;
use App\Service\SiteSettings;
use App\Service\TeamNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        SiteSettings $settings,
        RateLimiterFactoryInterface $bookingLimiter,
    ): Response {
        if (!$settings->isBookingEnabled()) {
            return $this->render('booking/closed.html.twig');
        }

        $order = $this->prefill($request, $tours);
        $user = $this->getUser();
        $form = $this->createForm(BookingType::class, $order, ['email_locked' => $user instanceof User]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$bookingLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'flash.too_many_requests');

                return $this->redirectToRoute('app_order_new');
            }

            $this->bookingService->place($order, $user instanceof User ? $user : null, $request->getLocale());

            return $this->redirectToRoute('app_order_show', ['token' => $order->getAccessToken(), 'created' => 1]);
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form,
            'order' => $order,
            'tour' => $order->getTour(),
        ]);
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
    public function show(string $token, Request $request, ReviewRepository $reviews): Response
    {
        $order = $this->findOrder($token);

        $response = $this->render('booking/show.html.twig', [
            'order' => $order,
            'just_created' => $request->query->getBoolean('created'),
            'review' => $reviews->findOneByOrder($order),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();

        return $response;
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

        if (OrderStatus::COMPLETED !== $order->getStatus() && $order->isUpcoming()) {
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
            $reviews->save($review);
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
