<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

use App\DTO\DTO;

/**
 * DTO for log viewing configuration.
 */
class LogViewDTO implements DTO
{
    public function __construct(
        public readonly string $content,
        public readonly int $lines,
        public readonly bool $fileExists,
        public readonly ?string $filePath = null,
    ) {
    }

    /**
     * Converts DTO to array for template rendering.
     */
    public function toArray(): array
    {
        return [
            'logContent' => $this->content,
            'lines' => $this->lines,
            'logExists' => $this->fileExists,
            'isEmpty' => $this->isEmpty(),
            'lineCount' => $this->getLineCount(),
        ];
    }

    public function isEmpty(): bool
    {
        return empty(trim($this->content));
    }

    public function getLineCount(): int
    {
        return $this->isEmpty() ? 0 : count(explode("\n", trim($this->content)));
    }

    /**
     * Validates line count parameter.
     */
    public static function validateLines(int $requestedLines): int
    {
        return max(50, min(1000, $requestedLines));
    }
}