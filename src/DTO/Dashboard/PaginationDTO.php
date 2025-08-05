<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

use App\DTO\DTO;

/**
 * DTO for pagination parameters.
 */
class PaginationDTO implements DTO
{
    public function __construct(
        public readonly int $currentPage,
        public readonly int $totalPages,
        public readonly int $totalItems,
        public readonly int $itemsPerPage,
        public readonly int $offset,
    ) {
    }

    /**
     * Creates PaginationDTO from request parameters.
     */
    public static function fromRequest(int $page, int $totalItems, int $itemsPerPage = 20): self
    {
        $currentPage = max(1, $page);
        $totalPages = (int) ceil($totalItems / $itemsPerPage);
        $offset = ($currentPage - 1) * $itemsPerPage;

        return new self($currentPage, $totalPages, $totalItems, $itemsPerPage, $offset);
    }

    /**
     * Converts DTO to array for template rendering.
     */
    public function toArray(): array
    {
        return [
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
            'totalItems' => $this->totalItems,
            'itemsPerPage' => $this->itemsPerPage,
            'hasNextPage' => $this->hasNextPage(),
            'hasPreviousPage' => $this->hasPreviousPage(),
            'startItem' => $this->getStartItem(),
            'endItem' => $this->getEndItem(),
        ];
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getStartItem(): int
    {
        return $this->totalItems > 0 ? $this->offset + 1 : 0;
    }

    public function getEndItem(): int
    {
        return min($this->offset + $this->itemsPerPage, $this->totalItems);
    }
}