<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

use App\DTO\DTO;

/**
 * DTO for dashboard statistics.
 */
class DashboardStatsDTO implements DTO
{
    public function __construct(
        public readonly int $totalUsers,
        public readonly int $totalOrders,
        public readonly int $failedEmails,
        public readonly int $confirmedUsers,
    ) {
    }

    /**
     * Converts DTO to array for template rendering.
     */
    public function toArray(): array
    {
        return [
            'total_users' => $this->totalUsers,
            'total_orders' => $this->totalOrders,
            'failed_emails' => $this->failedEmails,
            'confirmed_users' => $this->confirmedUsers,
        ];
    }

    /**
     * Calculates additional metrics.
     */
    public function getConfirmationRate(): float
    {
        return $this->totalUsers > 0 ? ($this->confirmedUsers / $this->totalUsers) * 100 : 0;
    }

    public function hasFailedEmails(): bool
    {
        return $this->failedEmails > 0;
    }
}