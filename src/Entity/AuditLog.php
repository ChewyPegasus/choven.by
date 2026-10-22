<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Who changed what in the admin panel. Written by App\EventListener\AuditSubscriber and App\Service\AuditLogger.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class, readOnly: true)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'idx_audit_log_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_audit_log_entity', columns: ['entity_type', 'entity_id'])]
class AuditLog
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userEmail = null;

    /** create, update, delete or an event name such as "login" or "payment_refund". */
    #[ORM\Column(length: 40)]
    private string $action = '';

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $entityId = null;

    /** Human readable name of the object (booking reference, tour title…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    /** @var array<string, mixed>|null field => [old, new] */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changes = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }
}
