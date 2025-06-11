<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Order;
use App\Enum\EmailTemplate;
use App\Enum\Package;
use App\Enum\River;
use DateInterval;
use DateTime;

readonly class OrderDTO extends AbstractEmailDTO
{
    private string $email;
    private string $description;
    private River $river;
    private int $amountOfPeople;
    private Package $package;
    private DateTime $startDate;
    private DateInterval $duration;
    private int $id;
    
    public function __construct(Order $order)
    {
        $this->email = $order->getEmail();
        $this->description = $order->getDescription();
        $this->river = $order->getRiver();
        $this->amountOfPeople = $order->getAmountOfPeople();
        $this->package = $order->getPackage();
        $this->startDate = $order->getStartDate();
        $this->duration = $order->getDuration();
        $this->id = $order->getId();
    }

    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getDescription(): string
    {
        return $this->description;
    }
    
    public function getRiver(): River
    {
        return $this->river;
    }
    
    public function getAmountOfPeople(): int
    {
        return $this->amountOfPeople;
    }
    
    public function getPackage(): Package
    {
        return $this->package;
    }
    
    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }
    
    public function getDuration(): DateInterval
    {
        return $this->duration;
    }
    
    public function getId(): int
    {
        return $this->id;
    }

    public function getEmailTemplate(): EmailTemplate
    {
        return EmailTemplate::ORDER_CONFIRMATION;
    }
    
    public function getContext(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'river' => $this->river,
            'amountOfPeople' => $this->amountOfPeople,
            'package' => $this->package,
            'startDate' => $this->startDate,
            'duration' => $this->duration,
            'email' => $this->email
        ];
    }

    public function getDurationDays(): int
    {
        return $this->duration->d;
    }
}
