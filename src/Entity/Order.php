<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;
use App\Repository\OrderRepository;
use App\Validator\PhoneNumber as PhoneNumberConstraint;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_orders_start_date', columns: ['start_date'])]
#[ORM\Index(name: 'idx_orders_status', columns: ['status'])]
#[ORM\Index(name: 'idx_orders_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    public const MIN_PEOPLE = 1;
    public const MAX_PEOPLE = 50;
    public const MIN_DAYS = 1;
    public const MAX_DAYS = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Short code shown to customers and used in support conversations, e.g. "CH-7K3P9Q". */
    #[ORM\Column(length: 16, unique: true)]
    private string $reference;

    /** Secret part of the link that lets a guest (not logged in) view or cancel the booking. */
    #[ORM\Column(length: 64, unique: true)]
    private string $accessToken;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Tour::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tour $tour = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'order.error.name_required')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'order.error.email_required')]
    #[Assert\Email(message: 'order.error.email_invalid')]
    #[Assert\Length(max: 180)]
    private string $email = '';

    /** Nullable in the database only because bookings imported from the old system have no phone. */
    #[ORM\Column(type: 'phone_number', length: 20, nullable: true)]
    #[Assert\NotBlank(message: 'order.error.phone_required', groups: ['booking'])]
    #[PhoneNumberConstraint]
    private ?PhoneNumber $phone = null;

    #[ORM\Column(length: 20, enumType: River::class)]
    #[Assert\NotNull(message: 'order.error.river_required')]
    private ?River $river = null;

    #[ORM\Column(length: 20, enumType: Package::class)]
    #[Assert\NotNull(message: 'order.error.package_required')]
    private ?Package $package = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'order.error.date_required')]
    #[Assert\GreaterThanOrEqual('today', message: 'order.error.date_in_past', groups: ['booking'])]
    #[Assert\LessThanOrEqual('+1 year', message: 'order.error.date_too_far', groups: ['booking'])]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'order.error.duration_required')]
    #[Assert\Range(notInRangeMessage: 'order.error.duration_range', min: self::MIN_DAYS, max: self::MAX_DAYS)]
    private ?int $durationDays = 1;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'order.error.people_required')]
    #[Assert\Range(notInRangeMessage: 'order.error.people_range', min: self::MIN_PEOPLE, max: self::MAX_PEOPLE)]
    private ?int $amountOfPeople = 2;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $adminNote = null;

    #[ORM\Column(length: 20, enumType: OrderStatus::class, options: ['default' => 'new'])]
    private OrderStatus $status = OrderStatus::NEW;

    /**
     * Total in BYN calculated at booking time: trip + add-ons − promo discount − gift certificate.
     * The final price is agreed by phone; administrators may correct it.
     */
    #[ORM\Column(nullable: true)]
    private ?int $estimatedPrice = null;

    #[ORM\Column(length: 5, options: ['default' => 'ru'])]
    private string $locale = 'ru';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    /** When the "your trip is in two days" email was sent, so it is sent only once. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    /** Price of the trip itself (tour price × people) before add-ons and discounts. */
    #[ORM\Column(nullable: true)]
    private ?int $basePrice = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $extrasPrice = 0;

    #[ORM\ManyToOne(targetEntity: PromoCode::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PromoCode $promoCode = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $discount = 0;

    #[ORM\ManyToOne(targetEntity: GiftCertificate::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GiftCertificate $giftCertificate = null;

    /** Part of the price covered by the gift certificate. */
    #[ORM\Column(options: ['default' => 0])]
    private int $giftAmount = 0;

    /** Online prepayment asked for this booking; null when no prepayment is needed. */
    #[ORM\Column(nullable: true)]
    private ?int $depositAmount = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $paidAmount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $rescheduleCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $rescheduledAt = null;

    /** @var Collection<int, OrderExtra> */
    #[ORM\OneToMany(targetEntity: OrderExtra::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $extras;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'order')]
    #[ORM\OrderBy(['createdAt' => \SortDirection::Ascending])]
    private Collection $payments;

    public function __construct()
    {
        $this->reference = self::generateReference();
        $this->accessToken = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->extras = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    /**
     * Unambiguous alphabet: no 0/O, 1/I/L.
     */
    public static function generateReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 6; ++$i) {
            $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }

        return 'CH-'.$code;
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

    public function getReference(): string
    {
        return $this->reference;
    }

    public function regenerateReference(): void
    {
        $this->reference = self::generateReference();
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTour(): ?Tour
    {
        return $this->tour;
    }

    public function setTour(?Tour $tour): static
    {
        $this->tour = $tour;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = mb_strtolower(trim((string) $email));

        return $this;
    }

    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function setPhone(?PhoneNumber $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhoneFormatted(): ?string
    {
        return null !== $this->phone
            ? PhoneNumberUtil::getInstance()->format($this->phone, PhoneNumberFormat::INTERNATIONAL)
            : null;
    }

    public function getPhoneE164(): ?string
    {
        return null !== $this->phone
            ? PhoneNumberUtil::getInstance()->format($this->phone, PhoneNumberFormat::E164)
            : null;
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

    public function getPackage(): ?Package
    {
        return $this->package;
    }

    public function setPackage(?Package $package): static
    {
        $this->package = $package;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate?->setTime(0, 0);

        return $this;
    }

    /**
     * Last day of the trip (inclusive).
     */
    public function getEndDate(): ?\DateTimeImmutable
    {
        if (null === $this->startDate) {
            return null;
        }

        return $this->startDate->modify(\sprintf('+%d days', max(0, ($this->durationDays ?? 1) - 1)));
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

    public function getAmountOfPeople(): ?int
    {
        return $this->amountOfPeople;
    }

    public function setAmountOfPeople(?int $amountOfPeople): static
    {
        $this->amountOfPeople = $amountOfPeople;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = null !== $description ? trim($description) : null;
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): static
    {
        $this->adminNote = $adminNote;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        if (OrderStatus::CANCELLED === $status && OrderStatus::CANCELLED !== $this->status) {
            $this->cancelledAt = new \DateTimeImmutable();
        } elseif (OrderStatus::CANCELLED !== $status) {
            $this->cancelledAt = null;
        }

        $this->status = $status;

        return $this;
    }

    public function isUpcoming(): bool
    {
        return null !== $this->startDate && $this->startDate >= new \DateTimeImmutable('today');
    }

    public function canBeCancelledByCustomer(): bool
    {
        return $this->status->isCancellableByCustomer() && $this->isUpcoming();
    }

    public function getEstimatedPrice(): ?int
    {
        return $this->estimatedPrice;
    }

    public function setEstimatedPrice(?int $estimatedPrice): static
    {
        $this->estimatedPrice = $estimatedPrice;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function markReminderSent(): static
    {
        $this->reminderSentAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Whole days from $today to the start of the trip (negative when it has started).
     */
    public function getDaysUntilStart(\DateTimeImmutable $today): int
    {
        if (null === $this->startDate) {
            return 0;
        }

        $diff = $today->setTime(0, 0)->diff($this->startDate);

        return (int) $diff->days * ($diff->invert ? -1 : 1);
    }

    public function reschedule(\DateTimeImmutable $startDate): static
    {
        $this->setStartDate($startDate);
        $this->reminderSentAt = null;
        $this->rescheduledAt = new \DateTimeImmutable();
        ++$this->rescheduleCount;

        return $this;
    }

    public function getRescheduleCount(): int
    {
        return $this->rescheduleCount;
    }

    public function getRescheduledAt(): ?\DateTimeImmutable
    {
        return $this->rescheduledAt;
    }

    public function getBasePrice(): ?int
    {
        return $this->basePrice;
    }

    public function setBasePrice(?int $basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getExtrasPrice(): int
    {
        return $this->extrasPrice;
    }

    public function setExtrasPrice(int $extrasPrice): static
    {
        $this->extrasPrice = $extrasPrice;

        return $this;
    }

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function setPromoCode(?PromoCode $promoCode): static
    {
        $this->promoCode = $promoCode;

        return $this;
    }

    public function getDiscount(): int
    {
        return $this->discount;
    }

    public function setDiscount(int $discount): static
    {
        $this->discount = $discount;

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

    public function getGiftAmount(): int
    {
        return $this->giftAmount;
    }

    public function setGiftAmount(int $giftAmount): static
    {
        $this->giftAmount = $giftAmount;

        return $this;
    }

    public function getDepositAmount(): ?int
    {
        return $this->depositAmount;
    }

    public function setDepositAmount(?int $depositAmount): static
    {
        $this->depositAmount = $depositAmount > 0 ? $depositAmount : null;

        return $this;
    }

    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function addPaid(int $amount): static
    {
        $this->paidAmount = max(0, $this->paidAmount + $amount);
        if ($amount > 0) {
            $this->paidAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * Prepayment that still has to be paid online.
     */
    public function getDepositDue(): int
    {
        return null === $this->depositAmount ? 0 : max(0, $this->depositAmount - $this->paidAmount);
    }

    public function isDepositPaid(): bool
    {
        return null !== $this->depositAmount && $this->paidAmount >= $this->depositAmount;
    }

    /**
     * @return Collection<int, OrderExtra>
     */
    public function getExtras(): Collection
    {
        return $this->extras;
    }

    public function addExtra(OrderExtra $extra): static
    {
        if (!$this->extras->contains($extra)) {
            $this->extras->add($extra);
        }

        return $this;
    }

    public function clearExtras(): static
    {
        $this->extras->clear();

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }
}
