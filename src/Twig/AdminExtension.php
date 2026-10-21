<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\OrderStatus;
use App\Repository\ContactRequestRepository;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Service\FailedMessages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Badges of the admin sidebar ("3 new bookings", "1 review to moderate", …).
 */
final class AdminExtension extends AbstractExtension
{
    /** @var array<string, int>|null */
    private ?array $counters = null;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ReviewRepository $reviews,
        private readonly ContactRequestRepository $contactRequests,
        private readonly FailedMessages $failedMessages,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('admin_counters', $this->getCounters(...))];
    }

    /**
     * @return array<string, int>
     */
    public function getCounters(): array
    {
        return $this->counters ??= [
            'orders' => $this->orders->count(['status' => OrderStatus::NEW]),
            'reviews' => $this->reviews->countPending(),
            'requests' => $this->contactRequests->countUnprocessed(),
            'emails' => $this->failedMessages->count(),
        ];
    }
}
