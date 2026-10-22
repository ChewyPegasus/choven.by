<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WaitlistEntryRepository;
use App\Validator\PhoneNumber as PhoneNumberConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * "Tell me when places become free": left by a visitor when the chosen dates are sold out.
 */
#[ORM\Entity(repositoryClass: WaitlistEntryRepository::class)]
#[ORM\Table(name: 'waitlist_entries')]
#[ORM\Index(name: 'idx_waitlist_start_date', columns: ['start_date'])]
class WaitlistEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: Tour::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tour $tour = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual('today')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: Order::MIN_DAYS, max: Order::MAX_DAYS)]
    private int $durationDays = 1;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: Order::MIN_PEOPLE, max: Order::MAX_PEOPLE)]
    private int $people = 2;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'order.error.name_required')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'order.error.email_required')]
    #[Assert\Email(message: 'order.error.email_invalid')]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(type: 'phone_number', length: 20, nullable: true)]
    #[PhoneNumberConstraint]
    private ?PhoneNumber $phone = null;

    #[ORM\Column(length: 5, options: ['default' => 'ru'])]
    private string $locale = 'ru';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromOrder(Order $order): self
    {
        $entry = new self();
        $entry->tour = $order->getTour();
        $entry->startDate = $order->getStartDate();
        $entry->durationDays = max(Order::MIN_DAYS, min(Order::MAX_DAYS, (int) $order->getDurationDays()));
        $entry->people = max(Order::MIN_PEOPLE, min(Order::MAX_PEOPLE, (int) $order->getAmountOfPeople()));
        $entry->setName($order->getName());
        $entry->setEmail($order->getEmail());
        $entry->phone = $order->getPhone();

        return $entry;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate?->setTime(0, 0);

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->startDate?->add(new \DateInterval(\sprintf('P%dD', max(0, $this->durationDays - 1))));
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): static
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    public function getPeople(): int
    {
        return $this->people;
    }

    public function setPeople(int $people): static
    {
        $this->people = $people;

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

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function markNotified(): static
    {
        $this->notifiedAt = new \DateTimeImmutable();

        return $this;
    }
}
