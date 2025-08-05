<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

use App\DTO\DTO;

/**
 * DTO for route file information.
 */
class RouteFileDTO implements DTO
{
    public function __construct(
        public readonly string $id,
        public readonly int $size,
        public readonly \DateTime $modified,
    ) {
    }

    /**
     * Converts DTO to array for template rendering.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'size' => $this->size,
            'modified' => $this->modified,
            'sizeFormatted' => $this->getFormattedSize(),
            'modifiedFormatted' => $this->modified->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Returns human-readable file size.
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Checks if file was modified recently (within last 24 hours).
     */
    public function isRecentlyModified(): bool
    {
        $now = new \DateTime();
        $interval = $now->diff($this->modified);
        return $interval->days === 0;
    }
}