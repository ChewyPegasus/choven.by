<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\River;
use App\Enum\Type;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(enumType: River::class)]
    private ?River $river = null;

    #[ORM\Column]
    private ?int $amountOfPeople = null;

    #[ORM\Column(enumType: Type::class)]
    private ?Type $type = null;

    #[ORM\Column]
    private ?\DateTime $startDate = null;

    #[ORM\Column]
    private ?\DateInterval $duration = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRiver(): ?River
    {
        return $this->river;
    }

    public function setRiver(River $river): static
    {
        $this->river = $river;

        return $this;
    }

    public function getAmountOfPeople(): ?int
    {
        return $this->amountOfPeople;
    }

    public function setAmountOfPeople(int $amountOfPeople): static
    {
        $this->amountOfPeople = $amountOfPeople;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getDuration(): ?\DateInterval
    {
        return $this->duration;
    }

    public function setDuration(\DateInterval $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getDurationDays(): int
    {
        return $this->duration ? $this->duration->d : 1;
    }

    public function setDurationDays(int $days): self
    {
        $this->duration = new \DateInterval("P{$days}D");
        return $this;
    }
}
