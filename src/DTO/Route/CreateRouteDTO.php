<?php

declare(strict_types=1);

namespace App\DTO\Route;

use App\DTO\DTO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating new routes via API.
 */
class CreateRouteDTO implements DTO
{
    #[Assert\NotBlank(message: 'Route ID is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Route ID must be at least 2 characters', maxMessage: 'Route ID must not exceed 50 characters')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/', message: 'Route ID can only contain letters, numbers, underscores and hyphens')]
    public ?string $id = null;

    #[Assert\NotBlank(message: 'Route data is required')]
    public ?array $data = null;

    /**
     * Validates that the data contains required route fields.
     */
    #[Assert\Callback]
    public function validateRouteData(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->data === null) {
            return;
        }

        $requiredFields = ['name', 'description', 'points'];
        foreach ($requiredFields as $field) {
            if (!isset($this->data[$field]) || empty($this->data[$field])) {
                $context->buildViolation("Route data must contain '{$field}' field")
                    ->atPath('data')
                    ->addViolation();
            }
        }

        // Validate points structure
        if (isset($this->data['points']) && is_array($this->data['points'])) {
            if (count($this->data['points']) < 2) {
                $context->buildViolation('Route must have at least 2 points')
                    ->atPath('data.points')
                    ->addViolation();
            }

            foreach ($this->data['points'] as $index => $point) {
                if (!isset($point['coordinates']) || !is_array($point['coordinates']) || count($point['coordinates']) !== 2) {
                    $context->buildViolation("Point {$index} must have valid coordinates array [lat, lng]")
                        ->atPath("data.points.{$index}")
                        ->addViolation();
                }
            }
        }
    }
}