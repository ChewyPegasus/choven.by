<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GiftCertificateStatus;
use App\Repository\GiftCertificateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prepaid gift certificate: bought on the site (or issued by the team) and redeemed in the booking form.
 */
#[ORM\Entity(repositoryClass: GiftCertificateRepository::class)]
#[ORM\Table(name: 'gift_certificates')]
#[ORM\Index(name: 'idx_gift_certificates_status', columns: ['status'])]
class GiftCertificate
{
    public const MIN_AMOUNT = 30;
    public const MAX_AMOUNT = 3000;
    public const VALIDITY = '+1 year';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** What the recipient types into the booking form, e.g. "GIFT-7K3P-9QXA". */
    #[ORM\Column(length: 20, unique: true)]
    private string $code;

    /** Secret part of the link to the printable certificate. */
    #[ORM\Column(length: 32, unique: true)]
    private string $token;

    #[ORM\Column]
    #[Assert\Range(min: self::MIN_AMOUNT, max: self::MAX_AMOUNT)]
    private int $amount = 100;

    #[ORM\Column]
    private int $balance = 0;

    #[ORM\Column(length: 12, enumType: GiftCertificateStatus::class)]
    private GiftCertificateStatus $status = GiftCertificateStatus::PENDING;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $buyerName = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $buyerEmail = '';

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $recipientName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $message = null;

    #[ORM\Column(length: 5, options: ['default' => 'ru'])]
    private string $locale = 'ru';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $activatedAt = null;

    public function __construct()
    {
        $this->code = self::generateCode();
        $this->token = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(self::VALIDITY)->setTime(0, 0);
    }

    public static function generateCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $part = static function () use ($alphabet): string {
            $s = '';
            for ($i = 0; $i < 4; ++$i) {
                $s .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }

            return $s;
        };

        return 'GIFT-'.$part().'-'.$part();
    }

    public function regenerateCode(): void
    {
        $this->code = self::generateCode();
    }

    public function activate(): static
    {
        if (GiftCertificateStatus::PENDING === $this->status) {
            $this->status = GiftCertificateStatus::ACTIVE;
            $this->balance = $this->amount;
            $this->activatedAt = new \DateTimeImmutable();
            // The year of validity starts when the certificate is paid.
            $this->expiresAt = $this->activatedAt->modify(self::VALIDITY)->setTime(0, 0);
        }

        return $this;
    }

    public function isRedeemable(\DateTimeImmutable $today): bool
    {
        return GiftCertificateStatus::ACTIVE === $this->status && $this->balance > 0 && $this->expiresAt >= $today->setTime(0, 0);
    }

    /**
     * Takes up to $amount from the balance and returns how much was taken.
     */
    public function redeem(int $amount): int
    {
        $taken = max(0, min($amount, $this->balance));
        $this->balance -= $taken;
        if (0 === $this->balance && GiftCertificateStatus::ACTIVE === $this->status) {
            $this->status = GiftCertificateStatus::USED;
        }

        return $taken;
    }

    /**
     * Gives back an amount taken by a booking that was cancelled.
     */
    public function restore(int $amount): static
    {
        if ($amount <= 0 || GiftCertificateStatus::CANCELLED === $this->status || GiftCertificateStatus::PENDING === $this->status) {
            return $this;
        }

        $this->balance = min($this->amount, $this->balance + $amount);
        if ($this->balance > 0) {
            $this->status = GiftCertificateStatus::ACTIVE;
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        if (GiftCertificateStatus::PENDING === $this->status) {
            $this->balance = 0;
        }

        return $this;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getStatus(): GiftCertificateStatus
    {
        return $this->status;
    }

    public function cancel(): static
    {
        $this->status = GiftCertificateStatus::CANCELLED;

        return $this;
    }

    public function getBuyerName(): string
    {
        return $this->buyerName;
    }

    public function setBuyerName(?string $buyerName): static
    {
        $this->buyerName = trim((string) $buyerName);

        return $this;
    }

    public function getBuyerEmail(): string
    {
        return $this->buyerEmail;
    }

    public function setBuyerEmail(?string $buyerEmail): static
    {
        $this->buyerEmail = mb_strtolower(trim((string) $buyerEmail));

        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): static
    {
        $recipientName = null !== $recipientName ? trim($recipientName) : null;
        $this->recipientName = '' === $recipientName ? null : $recipientName;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $message = null !== $message ? trim($message) : null;
        $this->message = '' === $message ? null : $message;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt->setTime(0, 0);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getActivatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }
}
