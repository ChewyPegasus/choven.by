<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ContactRequestRepository;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Service\FailedMessages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        OrderRepository $orders,
        UserRepository $users,
        ReviewRepository $reviews,
        ContactRequestRepository $contactRequests,
        FailedMessages $failedMessages,
    ): Response {
        $monthAgo = new \DateTimeImmutable('-30 days');
        $monthly = $orders->getMonthlyStats(12);
        $maxPeople = max(1, ...array_map(static fn (array $m): int => $m['people'], array_values($monthly)));

        return $this->render('admin/dashboard.html.twig', [
            'by_status' => $orders->countByStatus(),
            'orders_30d' => $orders->countSince($monthAgo),
            'users_total' => $users->count([]),
            'users_30d' => $users->countCreatedSince($monthAgo),
            'reviews_pending' => $reviews->countPending(),
            'rating' => $reviews->getRatingSummary(),
            'requests_open' => $contactRequests->countUnprocessed(),
            'failed_emails' => $failedMessages->count(),
            'upcoming' => $orders->findUpcoming(8),
            'monthly' => $monthly,
            'monthly_max' => $maxPeople,
        ]);
    }
}
