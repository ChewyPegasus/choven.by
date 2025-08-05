<?php

declare(strict_types=1);

namespace App\DTO\Order;

use App\DTO\DTO;
use App\Enum\Package;
use App\Enum\River;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for updating order data.
 */
class UpdateOrderDTO implements DTO
{
    #[Assert\Email(message: 'order.form.error.email_invalid')]
    public ?string $email = null;

    #[Assert\Date(message: 'order.form.error.date_invalid')]
    public ?string $startDate = null;

    public ?River $river = null;

    public ?Package $package = null;

    #[Assert\Range(min: 1, max: 50, notInRangeMessage: 'order.form.error.people_range')]
    public ?int $amountOfPeople = null;

    #[Assert\Range(min: 1, max: 7, notInRangeMessage: 'order.form.error.duration_range')]
    public ?int $durationDays = null;

    public ?string $description = null;
}