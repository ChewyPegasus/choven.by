<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;
use App\Repository\OrderRepository;
use App\Validator\PhoneNumber as PhoneNumberConstraint;
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
    #[Assert\NotBlank(message: 'order.error.phone_required')]
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

    /** Rough price in BYN calculated at booking time from the tour price; the final price is agreed by phone. */
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

    public function __construct()
    {
        $this->reference = self::generateReference();
        $this->accessToken = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
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
}
