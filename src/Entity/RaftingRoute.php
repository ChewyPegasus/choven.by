<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\River;
use App\Enum\RouteDifficulty;
use App\Model\Localized;
use App\Repository\RaftingRouteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A route drawn on the interactive map of the landing page.
 *
 * Points are stored as a JSON list:
 *   [{lat: float, lng: float, type: "start"|"point"|"end", name: {ru: "..."}, description: {ru: "..."}}, ...]
 */
#[ORM\Entity(repositoryClass: RaftingRouteRepository::class)]
#[ORM\Table(name: 'rafting_routes')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'route.slug.already_used')]
class RaftingRoute
{
    public const POINT_TYPES = ['start', 'point', 'end'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'route.slug.invalid')]
    private string $slug = '';

    #[ORM\Column(length: 20, nullable: true, enumType: River::class)]
    private ?River $river = null;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $name = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $description = [];

    /** @var array<string, string> Human readable duration, e.g. {"ru": "4–6 часов"} */
    #[ORM\Column(type: Types::JSON)]
    private array $duration = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 1, nullable: true)]
    #[Assert\Positive]
    private ?string $distanceKm = null;

    #[ORM\Column(length: 10, enumType: RouteDifficulty::class)]
    private RouteDifficulty $difficulty = RouteDifficulty::EASY;

    #[ORM\Column(length: 7)]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG)]
    private string $color = '#ff6b00';

    /** @var list<array{lat: float, lng: float, type: string, name: array<string, string>, description: array<string, string>}> */
    #[ORM\Column(type: Types::JSON)]
    private array $points = [];

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
    public function validate(ExecutionContextInterface $context): void
    {
        if ('' === Localized::pick($this->name, Localized::DEFAULT_LOCALE)) {
            $context->buildViolation('localized.default_required')->atPath('name')->addViolation();
        }

        if (\count($this->points) < 2) {
            $context->buildViolation('route.points.min_count')->atPath('points')->addViolation();
        }

        foreach ($this->points as $index => $point) {
            $lat = $point['lat'];
            $lng = $point['lng'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $context->buildViolation('route.points.invalid_coordinates')
                    ->atPath(\sprintf('points[%d]', $index))
                    ->addViolation();
            }
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

    public function getRiver(): ?River
    {
        return $this->river;
    }

    public function setRiver(?River $river): static
    {
        $this->river = $river;

        return $this;
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

    public function getLocalizedDescription(?string $locale = null): string
    {
        return Localized::pick($this->description, $locale);
    }

    /**
     * @return array<string, string>
     */
    public function getDuration(): array
    {
        return $this->duration;
    }

    /**
     * @param array<string, mixed> $duration
     */
    public function setDuration(array $duration): static
    {
        $this->duration = Localized::normalize($duration);

        return $this;
    }

    public function getLocalizedDuration(?string $locale = null): string
    {
        return Localized::pick($this->duration, $locale);
    }

    public function getDistanceKm(): ?float
    {
        return null !== $this->distanceKm ? (float) $this->distanceKm : null;
    }

    public function setDistanceKm(?float $distanceKm): static
    {
        $this->distanceKm = null !== $distanceKm ? number_format($distanceKm, 1, '.', '') : null;

        return $this;
    }

    public function getDifficulty(): RouteDifficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(RouteDifficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = mb_strtolower(trim((string) $color));

        return $this;
    }

    /**
     * @return list<array{lat: float, lng: float, type: string, name: array<string, string>, description: array<string, string>}>
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @param iterable<array<string, mixed>> $points
     */
    public function setPoints(iterable $points): static
    {
        $normalized = [];
        foreach ($points as $point) {
            if (!isset($point['lat'], $point['lng']) || !is_numeric($point['lat']) || !is_numeric($point['lng'])) {
                continue;
            }

            $type = \is_string($point['type'] ?? null) && \in_array($point['type'], self::POINT_TYPES, true) ? $point['type'] : 'point';
            $normalized[] = [
                'lat' => round((float) $point['lat'], 6),
                'lng' => round((float) $point['lng'], 6),
                'type' => $type,
                'name' => Localized::normalize(\is_array($point['name'] ?? null) ? $point['name'] : []),
                'description' => Localized::normalize(\is_array($point['description'] ?? null) ? $point['description'] : []),
            ];
        }

        $this->points = $normalized;

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
