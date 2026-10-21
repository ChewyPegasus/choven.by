<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;

/**
 * Search criteria of the admin order list (bound to App\Form\Admin\OrderFilterType).
 */
final class OrderFilter
{
    public ?string $query = null;
    public ?OrderStatus $status = null;
    public ?River $river = null;
    public ?Package $package = null;
    public ?\DateTimeImmutable $from = null;
    public ?\DateTimeImmutable $to = null;
    public string $sort = 'start_desc';

    public const SORTS = ['start_desc', 'start_asc', 'created_desc', 'created_asc'];

    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->query,
            'status' => $this->status?->value,
            'river' => $this->river?->value,
            'package' => $this->package?->value,
            'from' => $this->from?->format('Y-m-d'),
            'to' => $this->to?->format('Y-m-d'),
            'sort' => 'start_desc' !== $this->sort ? $this->sort : null,
        ], static fn (?string $value): bool => null !== $value && '' !== $value);
    }
}
