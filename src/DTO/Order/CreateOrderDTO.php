<?php

declare(strict_types=1);

namespace App\DTO\Order;

use App\DTO\DTO;
use App\Enum\Package;
use App\Enum\River;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating new orders via API.
 */
class CreateOrderDTO implements DTO
{
    #[Assert\NotBlank(message: 'order.form.error.email_required')]
    #[Assert\Email(message: 'order.form.error.email_invalid')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'order.form.error.date_required')]
    public ?string $startDate = null;

    #[Assert\NotBlank(message: 'order.form.error.river_required')]
    public ?River $river = null;

    #[Assert\NotBlank(message: 'order.form.error.type_required')]
    public ?Package $package = null;

    #[Assert\NotBlank(message: 'order.form.error.people_required')]
    #[Assert\Range(min: 1, max: 50, notInRangeMessage: 'order.form.error.people_range')]
    public ?int $amountOfPeople = null;

    #[Assert\NotBlank(message: 'order.form.error.duration_required')]
    #[Assert\Range(min: 1, max: 7, notInRangeMessage: 'order.form.error.duration_range')]
    public ?int $durationDays = null;

    public ?string $description = null;
}