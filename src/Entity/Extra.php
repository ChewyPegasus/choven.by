<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ExtraUnit;
use App\Model\Localized;
use App\Repository\ExtraRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Add-on service offered in the booking form: transfer, tent rental, photographer…
 */
#[ORM\Entity(repositoryClass: ExtraRepository::class)]
#[ORM\Table(name: 'extras')]
#[ORM\HasLifecycleCallbacks]
class Extra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $name = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $description = [];

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $price = 0;

    #[ORM\Column(length: 10, enumType: ExtraUnit::class)]
    private ExtraUnit $unit = ExtraUnit::PER_PERSON;

    /** The price is per day of the trip (rentals). */
    #[ORM\Column(options: ['default' => false])]
    private bool $perDay = false;

    /** Upper limit for the quantity of "per item" extras. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 10])]
    #[Assert\Range(min: 1, max: 50)]
    private int $maxQuantity = 10;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Regex(pattern: '/^fa-[a-z0-9-]+$/')]
    private ?string $icon = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateTranslations(ExecutionContextInterface $context): void
    {
        if ('' === Localized::pick($this->name, Localized::DEFAULT_LOCALE)) {
            $context->buildViolation('localized.default_required')->atPath('name')->addViolation();
        }
    }

    /**
     * Price of this extra for a booking.
     */
    public function priceFor(int $quantity, int $people, int $days): int
    {
        $count = match ($this->unit) {
            ExtraUnit::PER_ORDER => $quantity > 0 ? 1 : 0,
            ExtraUnit::PER_PERSON => $quantity > 0 ? max(1, $people) : 0,
            ExtraUnit::PER_ITEM => max(0, min($quantity, $this->maxQuantity)),
        };

        return $this->price * $count * ($this->perDay ? max(1, $days) : 1);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return array<string, string>
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @param array<string, mixed> $name
     */
    public function setName(array $name): static
    {
        $this->name = Localized::normalize($name);

        return $this;
    }

    public function getLocalizedName(?string $locale = null): string
    {
        return Localized::pick($this->name, $locale);
    }

    /**
     * @return array<string, string>
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    /**
     * @param array<string, mixed> $description
     */
    public function setDescription(array $description): static
    {
        $this->description = Localized::normalize($description);

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getUnit(): ExtraUnit
    {
        return $this->unit;
    }

    public function setUnit(ExtraUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function isPerDay(): bool
    {
        return $this->perDay;
    }

    public function setPerDay(bool $perDay): static
    {
        $this->perDay = $perDay;

        return $this;
    }

    public function getMaxQuantity(): int
    {
        return $this->maxQuantity;
    }

    public function setMaxQuantity(int $maxQuantity): static
    {
        $this->maxQuantity = $maxQuantity;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $icon = null !== $icon ? trim($icon) : null;
        $this->icon = '' === $icon ? null : $icon;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
