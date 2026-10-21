<?php

namespace App\Entity;

use App\Repository\FailedEmailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an email that failed to send and is stored for review or retry.
 *
 * This entity captures details of emails that encountered errors during sending,
 * including the email type, context data, the error message, and timestamps
 * for creation and the last failed attempt.
 */
#[ORM\Entity(repositoryClass: FailedEmailRepository::class)]
#[ORM\Table(name: 'failed')]
class FailedEmail
{
    /**
     * @var int|null The unique identifier for the failed email entry.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var string|null The type of email that failed (e.g., 'order_confirmation').
     */
    #[ORM\Column(length: 255)]
    private ?string $emailType = null;

    /**
     * @var array The context data that was used when attempting to send the email.
     */
    #[ORM\Column]
    private array $context = [];

    /**
     * @var string|null The error message or exception details from the failed sending attempt.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    /**
     * @var \DateTimeImmutable|null The timestamp when this failed email entry was created.
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var \DateTimeImmutable|null The timestamp of the last failed sending attempt.
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    /**
     * @var int|null The number of times sending this email has been attempted and failed.
     */
    #[ORM\Column]
    private ?int $attempts = null;

    /**
     * @var string|null The locale for which the email was intended.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $locale = null;

    /**
     * Constructs a new FailedEmail instance.
     *
     * Initializes the `createdAt` timestamp to the current time.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Gets the ID of the failed email entry.
     *
     * @return int|null The ID.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the email type.
     *
     * @return string|null The type of the failed email.
     */
    public function getEmailType(): ?string
    {
        return $this->emailType;
    }

    /**
     * Sets the email type.
     *
     * @param string $emailType The email type to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setEmailType(string $emailType): static
    {
        $this->emailType = $emailType;

        return $this;
    }

    /**
     * Gets the context data for the failed email.
     *
     * @return array The context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Sets the context data for the failed email.
     *
     * @param array $context The context data to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Gets the error message associated with the failure.
     *
     * @return string|null The error message.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Sets the error message for the failed email.
     *
     * @param string|null $error The error message to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setError(?string $error): static
    {
        $this->error = $error;

        return $this;
    }

    /**
     * Gets the creation timestamp of the failed email entry.
     *
     * @return \DateTimeImmutable|null The creation timestamp.
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation timestamp of the failed email entry.
     *
     * @param \DateTimeImmutable $createdAt The creation timestamp to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gets the timestamp of the last failed attempt.
     *
     * @return \DateTimeImmutable|null The last attempt timestamp.
     */
    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    /**
     * Sets the timestamp of the last failed attempt.
     *
     * @param \DateTimeImmutable $lastAttemptAt The last attempt timestamp to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setLastAttemptAt(\DateTimeImmutable $lastAttemptAt): static
    {
        $this->lastAttemptAt = $lastAttemptAt;

        return $this;
    }

    /**
     * Gets the number of failed attempts.
     *
     * @return int|null The number of attempts.
     */
    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    /**
     * Sets the number of failed attempts.
     *
     * @param int $attempts The number of attempts to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Gets the locale for the failed email.
     *
     * @return string|null The locale.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Sets the locale for the failed email.
     *
     * @param string|null $locale The locale to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }
}