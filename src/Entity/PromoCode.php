<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PromoType;
use App\Repository\PromoCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Discount code typed into the booking form ("SUMMER10").
 */
#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\Table(name: 'promo_codes')]
#[UniqueEntity(fields: ['code'], message: 'promo.code_taken')]
class PromoCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z0-9][A-Z0-9_-]{2,31}$/', message: 'promo.code_invalid')]
    private string $code = '';

    #[ORM\Column(length: 10, enumType: PromoType::class)]
    private PromoType $type = PromoType::PERCENT;

    #[ORM\Column]
    #[Assert\Positive]
    private int $value = 10;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    /** Total number of bookings that may use the code; null = unlimited. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $usedCount = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateValue(ExecutionContextInterface $context): void
    {
        if (PromoType::PERCENT === $this->type && $this->value > 100) {
            $context->buildViolation('promo.percent_range')->atPath('value')->addViolation();
        }
        if (null !== $this->validFrom && null !== $this->validUntil && $this->validUntil < $this->validFrom) {
            $context->buildViolation('promo.dates_order')->atPath('validUntil')->addViolation();
        }
    }

    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * Whether the code can be applied to a new booking today.
     */
    public function isUsable(\DateTimeImmutable $today): bool
    {
        $today = $today->setTime(0, 0);

        return $this->isActive
            && (null === $this->validFrom || $this->validFrom <= $today)
            && (null === $this->validUntil || $this->validUntil >= $today)
            && (null === $this->maxUses || $this->usedCount < $this->maxUses);
    }

    public function isUsableToday(): bool
    {
        return $this->isUsable(new \DateTimeImmutable('today'));
    }

    /**
     * Discount in BYN for the given amount (never more than the amount itself).
     */
    public function discountFor(int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $discount = PromoType::PERCENT === $this->type
            ? (int) round($amount * $this->value / 100)
            : $this->value;

        return min($amount, $discount);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = self::normalizeCode((string) $code);

        return $this;
    }

    public function getType(): PromoType
    {
        return $this->type;
    }

    public function setType(PromoType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): static
    {
        $this->maxUses = $maxUses;

        return $this;
    }

    public function getUsedCount(): int
    {
        return $this->usedCount;
    }

    public function incrementUsage(): static
    {
        ++$this->usedCount;

        return $this;
    }

    public function decrementUsage(): static
    {
        $this->usedCount = max(0, $this->usedCount - 1);

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
