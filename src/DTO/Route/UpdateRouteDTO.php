<?php

declare(strict_types=1);

namespace App\DTO\Route;

use App\DTO\DTO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for updating existing routes via API.
 */
class UpdateRouteDTO implements DTO
{
    #[Assert\NotBlank(message: 'route.form.error.data_required')]
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
                $context->buildViolation("route.form.error.{$field}_required")
                    ->atPath('data')
                    ->addViolation();
            }
        }

        // Validate points structure
        if (isset($this->data['points']) && is_array($this->data['points'])) {
            if (count($this->data['points']) < 2) {
                $context->buildViolation('route.form.error.points_min_count')
                    ->atPath('data.points')
                    ->addViolation();
            }

            foreach ($this->data['points'] as $index => $point) {
                if (!isset($point['coordinates']) || !is_array($point['coordinates']) || count($point['coordinates']) !== 2) {
                    $context->buildViolation('route.form.error.point_invalid_coordinates')
                        ->atPath("data.points.{$index}")
                        ->addViolation();
                }
            }
        }
    }
}