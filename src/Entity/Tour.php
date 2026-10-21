<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Package;
use App\Enum\River;
use App\Model\Localized;
use App\Repository\TourRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A ready-made trip offer shown as a card on the landing page and on its own page.
 */
#[ORM\Entity(repositoryClass: TourRepository::class)]
#[ORM\Table(name: 'tours')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'tour.slug.already_used')]
class Tour
{
    public const PRICE_PER_PERSON = 'person';
    public const PRICE_PER_GROUP = 'group';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'tour.slug.invalid')]
    private string $slug = '';

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $title = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $summary = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $description = [];

    #[ORM\Column(length: 20, enumType: Package::class)]
    #[Assert\NotNull]
    private ?Package $package = Package::ALL_INCLUSIVE;

    #[ORM\Column(length: 20, nullable: true, enumType: River::class)]
    private ?River $river = null;

    #[ORM\ManyToOne(targetEntity: RaftingRoute::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RaftingRoute $route = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: Order::MIN_DAYS, max: Order::MAX_DAYS)]
    private ?int $durationDays = 1;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $priceFrom = null;

    #[ORM\Column(length: 10, options: ['default' => self::PRICE_PER_PERSON])]
    #[Assert\Choice(choices: [self::PRICE_PER_PERSON, self::PRICE_PER_GROUP])]
    private string $priceUnit = self::PRICE_PER_PERSON;

    /**
     * Either an uploaded file ("uploads/tours/xxx.webp") or an AssetMapper path ("images/cards/xxx.jpg").
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

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
        if ('' === Localized::pick($this->title, Localized::DEFAULT_LOCALE)) {
            $context->buildViolation('localized.default_required')->atPath('title')->addViolation();
        }
        if ('' === Localized::pick($this->summary, Localized::DEFAULT_LOCALE)) {
            $context->buildViolation('localized.default_required')->atPath('summary')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = mb_strtolower(trim((string) $slug));

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTitle(): array
    {
        return $this->title;
    }

    /**
     * @param array<string, mixed> $title
     */
    public function setTitle(array $title): static
    {
        $this->title = Localized::normalize($title);

        return $this;
    }

    public function getLocalizedTitle(?string $locale = null): string
    {
        return Localized::pick($this->title, $locale);
    }

    /**
     * @return array<string, string>
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function setSummary(array $summary): static
    {
        $this->summary = Localized::normalize($summary);

        return $this;
    }

    public function getLocalizedSummary(?string $locale = null): string
    {
        return Localized::pick($this->summary, $locale);
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

    public function getLocalizedDescription(?string $locale = null): string
    {
        return Localized::pick($this->description, $locale);
    }

    public function getPackage(): ?Package
    {
        return $this->package;
    }

    public function setPackage(?Package $package): static
    {
        $this->package = $package;

        return $this;
    }

    public function getRiver(): ?River
    {
        return $this->river;
    }

    public function setRiver(?River $river): static
    {
        $this->river = $river;

        return $this;
    }

    public function getRoute(): ?RaftingRoute
    {
        return $this->route;
    }

    public function setRoute(?RaftingRoute $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(?int $durationDays): static
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    public function getPriceFrom(): ?int
    {
        return $this->priceFrom;
    }

    public function setPriceFrom(?int $priceFrom): static
    {
        $this->priceFrom = $priceFrom;

        return $this;
    }

    public function getPriceUnit(): string
    {
        return $this->priceUnit;
    }

    public function setPriceUnit(string $priceUnit): static
    {
        $this->priceUnit = $priceUnit;

        return $this;
    }

    public function isPricePerPerson(): bool
    {
        return self::PRICE_PER_PERSON === $this->priceUnit;
    }

    /**
     * Rough total for the booking form; null when the tour has no public price.
     */
    public function estimatePrice(int $people): ?int
    {
        if (null === $this->priceFrom) {
            return null;
        }

        return $this->isPricePerPerson() ? $this->priceFrom * max(1, $people) : $this->priceFrom;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
