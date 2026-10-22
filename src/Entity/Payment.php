<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One online payment attempt: a booking prepayment or the purchase of a gift certificate.
 *
 * Amounts are whole BYN, like all prices on the site.
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\Index(name: 'idx_payments_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Our id sent to the payment provider (tracking id) and used in return URLs. */
    #[ORM\Column(length: 32, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: GiftCertificate::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GiftCertificate $giftCertificate = null;

    #[ORM\Column(length: 20)]
    private string $provider;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 3, options: ['default' => 'BYN'])]
    private string $currency = 'BYN';

    #[ORM\Column(length: 12, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PENDING;

    /** Checkout token / session id at the provider. */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $externalId = null;

    /** Id of the successful transaction at the provider (needed for refunds). */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $redirectUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $refundedAmount = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $test = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct(string $provider, int $amount)
    {
        $this->publicId = bin2hex(random_bytes(16));
        $this->provider = $provider;
        $this->amount = $amount;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getGiftCertificate(): ?GiftCertificate
    {
        return $this->giftCertificate;
    }

    public function setGiftCertificate(?GiftCertificate $giftCertificate): static
    {
        $this->giftCertificate = $giftCertificate;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return PaymentStatus::PENDING === $this->status;
    }

    public function isSucceeded(): bool
    {
        return PaymentStatus::SUCCEEDED === $this->status;
    }

    public function markSucceeded(?string $transactionId): static
    {
        $this->status = PaymentStatus::SUCCEEDED;
        $this->transactionId = $transactionId ?? $this->transactionId;
        $this->paidAt ??= new \DateTimeImmutable();
        $this->error = null;

        return $this;
    }

    public function markFailed(PaymentStatus $status, ?string $error = null): static
    {
        $this->status = $status;
        $this->error = null !== $error ? mb_substr($error, 0, 255) : null;

        return $this;
    }

    public function markRefunded(int $amount): static
    {
        $this->refundedAmount = min($this->amount, $this->refundedAmount + $amount);
        if ($this->refundedAmount >= $this->amount) {
            $this->status = PaymentStatus::REFUNDED;
        }

        return $this;
    }

    public function getRefundableAmount(): int
    {
        return $this->isSucceeded() ? $this->amount - $this->refundedAmount : 0;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl(?string $redirectUrl): static
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getRefundedAmount(): int
    {
        return $this->refundedAmount;
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    public function setTest(bool $test): static
    {
        $this->test = $test;

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

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }
}
