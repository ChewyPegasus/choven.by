<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MessengerChannel;
use App\Repository\MessengerSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Telegram chat or a Viber user that receives booking notifications.
 *
 * Linked either to one booking (guests open a deep link from the booking page)
 * or to an account (then it covers all bookings of the user).
 */
#[ORM\Entity(repositoryClass: MessengerSubscriptionRepository::class)]
#[ORM\Table(name: 'messenger_subscriptions')]
#[ORM\Index(name: 'idx_messenger_subscriptions_external', columns: ['channel', 'external_id'])]
class MessengerSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: MessengerChannel::class)]
    private MessengerChannel $channel;

    /** Telegram chat id or Viber user id. */
    #[ORM\Column(length: 64)]
    private string $externalId;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $displayName;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(MessengerChannel $channel, string $externalId, ?string $displayName = null)
    {
        $this->channel = $channel;
        $this->externalId = $externalId;
        $this->displayName = null !== $displayName ? mb_substr($displayName, 0, 100) : null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): MessengerChannel
    {
        return $this->channel;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
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

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

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
}
