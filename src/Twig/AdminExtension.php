<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ContactRequestRepository;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\FailedMessages;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Badges of the admin sidebar ("3 new bookings", "1 review to moderate", …).
 * Counted once per request (reset() runs between requests in long-running workers).
 */
final class AdminExtension extends AbstractExtension implements ResetInterface
{
    /** @var array<string, int>|null */
    private ?array $counters = null;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ReviewRepository $reviews,
        private readonly ContactRequestRepository $contactRequests,
        private readonly FailedMessages $failedMessages,
        private readonly WaitlistEntryRepository $waitlist,
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
            'orders' => $this->orders->countNewUpcoming(),
            'reviews' => $this->reviews->countPending(),
            'requests' => $this->contactRequests->countUnprocessed(),
            'emails' => $this->failedMessages->count(),
            'waitlist' => $this->waitlist->countWaiting(new \DateTimeImmutable('today')),
        ];
    }

    public function reset(): void
    {
        $this->counters = null;
    }
}
