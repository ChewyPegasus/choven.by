<?php

declare(strict_types=1);

namespace App\DTO;

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

    #[Assert\Date]
    public ?string $startDate = null;

    public ?River $river = null;

    public ?Package $package = null;

    #[Assert\Range(min: 1, max: 50)]
    public ?int $amountOfPeople = null;

    #[Assert\Range(min: 1, max: 7)]
    public ?int $durationDays = null;

    public ?string $description = null;
}