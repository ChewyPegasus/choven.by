<?php

namespace App\Entity;

use App\Enum\River;
use App\Enum\Type;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
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

    #[ORM\Column]
    private ?\DateInterval $date = null;

    #[ORM\Column(enumType: River::class)]
    private ?River $river = null;

    #[ORM\Column]
    private ?int $amountOfPeople = null;

    #[ORM\Column(enumType: Type::class)]
    private ?Type $type = null;

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

    public function getDate(): ?\DateInterval
    {
        return $this->date;
    }

    public function setDate(\DateInterval $date): static
    {
        $this->date = $date;

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
}
