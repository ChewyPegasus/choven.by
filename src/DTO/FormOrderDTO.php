<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\Package;
use App\Enum\River;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object (DTO) for handling order form submissions.
 *
 * This DTO encapsulates the data submitted through the order form,
 * providing a structured way to transfer and validate this data.
 * It includes properties for email, start date, duration, river,
 * amount of people, package type, description, and locale, along with
 * Symfony validation constraints.
 */
class FormOrderDTO implements DTO
{
    #[Assert\NotBlank(message: 'order.form.error.email_required')]
    #[Assert\Email(message: 'order.form.error.email_invalid')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'order.form.error.date_required')]
    public ?\DateTime $startDate = null;

    #[Assert\NotBlank(message: 'order.form.error.duration_required')]
    #[Assert\Range(min: 1, max: 7, notInRangeMessage: 'order.form.error.duration_range')]
    public ?int $duration = null;

    #[Assert\NotBlank(message: 'order.form.error.river_required')]
    public ?River $river = null;

    #[Assert\NotBlank(message: 'order.form.error.people_required')]
    #[Assert\Range(min: 1, max: 50, notInRangeMessage: 'order.form.error.people_range')]
    public ?int $amountOfPeople = null;

    #[Assert\NotBlank(message: 'order.form.error.type_required')]
    public ?Package $package = null;

    public ?string $description = null;

    public ?string $locale = null;
}