<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes the audit log (admin actions). Rows are inserted with plain SQL, so logging never interferes
 * with the Doctrine unit of work and a failure to log never breaks the action itself.
 */
class AuditLogger
{
    /** Fields that must never end up in the log. */
    private const SECRET_FIELDS = ['password', 'totpSecret', 'backupCodes', 'accessToken', 'token'];

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Whether changes are made by an administrator (only those are audited).
     */
    public function isAdminActing(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * Records an event that is not a plain entity change ("login", "payment_refund", "2fa_disabled"…).
     *
     * @param array<string, mixed> $data
     * @param User|null            $actor who did it, when it is not (yet) the signed-in user (e.g. at login)
     */
    public function log(string $action, ?object $subject = null, array $data = [], ?User $actor = null): void
    {
        $this->write($action, $subject, null, [] === $data ? null : $this->normalizeChanges($data, false), $actor);
    }

    /**
     * @param array<string, mixed>|null $changes
     */
    public function write(string $action, ?object $subject, ?string $entityId = null, ?array $changes = null, ?User $actor = null): void
    {
        $user = $actor ?? $this->security->getUser();

        try {
            $this->connection->insert('audit_log', [
                'created_at' => new \DateTimeImmutable(),
                'user_id' => $user instanceof User ? $user->getId() : null,
                'user_email' => $user?->getUserIdentifier(),
                'action' => mb_substr($action, 0, 40),
                'entity_type' => null !== $subject ? (new \ReflectionClass($subject))->getShortName() : null,
                'entity_id' => $entityId ?? (null !== $subject ? $this->identifier($subject) : null),
                'label' => null !== $subject ? $this->label($subject) : null,
                'changes' => null !== $changes ? json_encode($changes, \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'ip' => $this->requestStack->getMainRequest()?->getClientIp(),
            ], [
                'created_at' => Types::DATETIME_IMMUTABLE,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Could not write the audit log: {message}', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $changes field => [old, new] (or field => value when $pairs is false)
     *
     * @return array<string, mixed>
     */
    public function normalizeChanges(array $changes, bool $pairs = true): array
    {
        $result = [];
        foreach ($changes as $field => $change) {
            if (\in_array($field, ['updatedAt', 'createdAt'], true)) {
                continue;
            }
            if (\in_array($field, self::SECRET_FIELDS, true)) {
                $result[$field] = '***';
                continue;
            }
            $result[$field] = $pairs && \is_array($change) && array_is_list($change) && 2 === \count($change)
                ? [$this->scalar($change[0]), $this->scalar($change[1])]
                : $this->scalar($change);
        }

        return $result;
    }

    public function identifier(object $entity): ?string
    {
        try {
            $values = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);
        } catch (\Throwable) {
            return null;
        }

        return [] === $values ? null : implode('-', array_map(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '?', $values));
    }

    public function label(object $entity): ?string
    {
        foreach (['getReference', 'getCode', 'getEmail', 'getLocalizedTitle', 'getLocalizedName', 'getSlug', 'getAuthorName', 'getName'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->{$method}();
                if (\is_string($value) && '' !== $value) {
                    return mb_substr($value, 0, 255);
                }
            }
        }

        return null;
    }

    private function scalar(mixed $value): mixed
    {
        return match (true) {
            null === $value, \is_scalar($value) => \is_string($value) ? mb_substr($value, 0, 500) : $value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof PhoneNumber => PhoneNumberUtil::getInstance()->format($value, PhoneNumberFormat::E164),
            \is_array($value) => mb_substr((string) json_encode($value, \JSON_UNESCAPED_UNICODE), 0, 500),
            \is_object($value) => (new \ReflectionClass($value))->getShortName().'#'.($this->identifier($value) ?? '?'),
            default => null,
        };
    }
}
