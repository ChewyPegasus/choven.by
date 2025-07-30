<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\River;
use App\Enum\Package;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a customer order for a river trip or package.
 *
 * This entity stores all details related to an order, including customer information,
 * chosen river, package, dates, and duration. It is mapped to the 'orders' table in the database.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    /**
     * @var int|null The unique identifier for the order.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var string|null An optional description or notes for the order.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var string|null The email address of the customer placing the order.
     */
    #[ORM\Column(length: 255)]
    private ?string $email = null;

    /**
     * @var River|null The river chosen for the trip, represented by a River enum.
     */
    #[ORM\Column(enumType: River::class)]
    private ?River $river = null;

    /**
     * @var int|null The number of people included in the order.
     */
    #[ORM\Column]
    private ?int $amountOfPeople = null;

    /**
     * @var Package|null The package type chosen for the order, represented by a Package enum.
     */
    #[ORM\Column(enumType: Package::class)]
    private ?Package $package = null;

    /**
     * @var \DateTime|null The start date of the order/trip.
     */
    #[ORM\Column]
    private ?\DateTime $startDate = null;

    /**
     * @var \DateInterval|null The duration of the trip.
     */
    #[ORM\Column]
    private ?\DateInterval $duration = null;

    /**
     * @var string|null The locale associated with the order (e.g., 'en', 'ru').
     */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    /**
     * @var User|null The User entity associated with this order, if placed by a registered user.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    /**
     * Gets the ID of the order.
     *
     * @return int|null The order ID.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the description of the order.
     *
     * @return string|null The order description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Sets the description of the order.
     *
     * @param string|null $description The description to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gets the email address for the order.
     *
     * @return string|null The customer's email address.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Sets the email address for the order.
     *
     * @param string $email The email address to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Gets the selected river for the order.
     *
     * @return River|null The River enum case.
     */
    public function getRiver(): ?River
    {
        return $this->river;
    }

    /**
     * Sets the river for the order.
     *
     * @param River $river The River enum case to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setRiver(River $river): static
    {
        $this->river = $river;

        return $this;
    }

    /**
     * Gets the amount of people for the order.
     *
     * @return int|null The number of people.
     */
    public function getAmountOfPeople(): ?int
    {
        return $this->amountOfPeople;
    }

    /**
     * Sets the amount of people for the order.
     *
     * @param int $amountOfPeople The number of people to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setAmountOfPeople(int $amountOfPeople): static
    {
        $this->amountOfPeople = $amountOfPeople;

        return $this;
    }

    /**
     * Gets the selected package for the order.
     *
     * @return Package|null The Package enum case.
     */
    public function getPackage(): ?Package
    {
        return $this->package;
    }

    /**
     * Sets the package for the order.
     *
     * @param Package|null $package The Package enum case to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setPackage(?Package $package): static
    {
        $this->package = $package;

        return $this;
    }

    /**
     * Gets the start date of the order.
     *
     * @return \DateTime|null The start date.
     */
    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    /**
     * Sets the start date of the order.
     *
     * @param \DateTime $startDate The start date to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Gets the duration of the order.
     *
     * @return \DateInterval|null The duration.
     */
    public function getDuration(): ?\DateInterval
    {
        return $this->duration;
    }

    /**
     * Sets the duration of the order.
     *
     * @param \DateInterval $duration The duration to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setDuration(\DateInterval $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Gets the duration of the order in days.
     *
     * Returns 1 if duration is not set or invalid, otherwise the number of days.
     *
     * @return int The duration in days.
     */
    public function getDurationDays(): int
    {
        return $this->duration ? $this->duration->d : 1;
    }

    /**
     * Sets the duration of the order based on a number of days.
     *
     * Creates a new \DateInterval object from the given number of days.
     *
     * @param int $days The number of days for the duration.
     * @return self Returns the current instance for method chaining.
     */
    public function setDurationDays(int $days): self
    {
        $this->duration = new \DateInterval("P{$days}D");
        
        return $this;
    }

    /**
     * Gets the locale for the order.
     *
     * @return string|null The locale.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Sets the locale for the order.
     *
     * @param string|null $locale The locale to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Gets the User entity associated with this order.
     *
     * @return User|null The associated User entity.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Sets the User entity associated with this order.
     *
     * @param User|null $user The User entity to associate.
     * @return static Returns the current instance for method chaining.
     */
    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}