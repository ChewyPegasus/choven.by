<?php

namespace App\Entity;

use App\Repository\EmailQueueRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an email entry in a queue for deferred or retried sending.
 *
 * This entity stores information about emails that need to be sent,
 * including their type, context data, number of sending attempts,
 * and timestamps for creation and last attempt.
 */
#[ORM\Entity(repositoryClass: EmailQueueRepository::class)]
#[ORM\Table(name: 'email_queue')]
class EmailQueue
{
    /**
     * @var int|null The unique identifier for the email queue entry.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var string|null The type of email (e.g., 'order_confirmation', 'password_reset').
     */
    #[ORM\Column(length: 255)]
    private ?string $emailType = null;

    /**
     * @var array The context data required to render the email template.
     * This typically includes variables like order details or user information.
     */
    #[ORM\Column]
    private array $context = [];

    /**
     * @var int The number of times sending this email has been attempted.
     */
    #[ORM\Column]
    private int $attempts = 0;

    /**
     * @var \DateTimeImmutable The timestamp when this email entry was created.
     */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var \DateTimeImmutable|null The timestamp of the last sending attempt.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    /**
     * @var string|null The locale for which the email should be sent.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $locale = null;

    /**
     * Constructs a new EmailQueue instance.
     *
     * Initializes the `createdAt` timestamp to the current time.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Gets the ID of the email queue entry.
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
     * @return string|null The email type.
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
     * Gets the context data for the email.
     *
     * @return array The context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Sets the context data for the email.
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
     * Gets the number of sending attempts.
     *
     * @return int|null The number of attempts.
     */
    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    /**
     * Sets the number of sending attempts.
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
     * Gets the creation timestamp.
     *
     * @return \DateTimeImmutable|null The creation timestamp.
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation timestamp.
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
     * Gets the timestamp of the last sending attempt.
     *
     * @return \DateTimeImmutable|null The last attempt timestamp.
     */
    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    /**
     * Sets the timestamp of the last sending attempt.
     *
     * @param \DateTimeImmutable|null $lastAttemptAt The last attempt timestamp to set.
     * @return static Returns the current instance for method chaining.
     */
    public function setLastAttemptAt(?\DateTimeImmutable $lastAttemptAt): static
    {
        $this->lastAttemptAt = $lastAttemptAt;

        return $this;
    }

    /**
     * Increments the attempt count and updates the last attempt timestamp.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function incrementAttempts(): self
    {
        $this->attempts++;
        $this->lastAttemptAt = new \DateTimeImmutable();
        
        return $this;
    }

    /**
     * Gets the locale for the email.
     *
     * @return string|null The locale.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Sets the locale for the email.
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