<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class OrderCreateDTO
{
    #[Assert\NotBlank]
    public string $email;

    #[Assert\NotBlank]
    public string $startDate;

    #[Assert\NotBlank]
    public string $river;

    #[Assert\NotBlank]
    public string $package;

    #[Assert\Positive]
    public int $amountOfPeople;

    #[Assert\Positive]
    public int $durationDays;

    public ?string $description = null;
}