<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactRequestRepository;
use App\Validator\PhoneNumber as PhoneNumberConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * "Call me back" / question form submitted from the site.
 */
#[ORM\Entity(repositoryClass: ContactRequestRepository::class)]
#[ORM\Table(name: 'contact_requests')]
#[ORM\Index(name: 'idx_contact_requests_processed', columns: ['is_processed'])]
class ContactRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'contact.error.name_required')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(type: 'phone_number', length: 20)]
    #[Assert\NotBlank(message: 'contact.error.phone_required')]
    #[PhoneNumberConstraint]
    private ?PhoneNumber $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $message = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isProcessed = false;

    #[ORM\Column(length: 5, options: ['default' => 'ru'])]
    private string $locale = 'ru';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = null !== $email ? mb_strtolower(trim($email)) : null;
        $this->email = '' === $email ? null : $email;

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

    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): static
    {
        $this->isProcessed = $isProcessed;
        $this->processedAt = $isProcessed ? new \DateTimeImmutable() : null;

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

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }
}
