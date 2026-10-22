<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\Extra;
use App\Entity\FaqItem;
use App\Entity\GiftCertificate;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Post;
use App\Entity\PromoCode;
use App\Entity\RaftingRoute;
use App\Entity\Review;
use App\Entity\Setting;
use App\Entity\Tour;
use App\Entity\User;
use App\Service\AuditLogger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Records every change administrators make to business data (who, when, field: old → new).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditSubscriber
{
    private const AUDITED = [
        Order::class, Tour::class, RaftingRoute::class, Review::class, User::class, Setting::class,
        PromoCode::class, GiftCertificate::class, Extra::class, FaqItem::class, Post::class, Payment::class,
    ];

    /** Changes nobody needs to see in the log. */
    private const IGNORED_FIELDS = ['lastLoginAt', 'reminderSentAt', 'updatedAt', 'trustedVersion'];

    /** @var list<array{action: string, entity: object, id: string|null, changes: array<string, mixed>}> */
    private array $pending = [];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->audit->isAdminActing()) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->isAudited($entity)) {
                $this->pending[] = ['action' => AuditLog::ACTION_CREATE, 'entity' => $entity, 'id' => null, 'changes' => $this->changes($uow->getEntityChangeSet($entity))];
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->isAudited($entity)) {
                continue;
            }
            $changes = $this->changes($uow->getEntityChangeSet($entity));
            if ([] !== $changes) {
                $this->pending[] = ['action' => AuditLog::ACTION_UPDATE, 'entity' => $entity, 'id' => $this->audit->identifier($entity), 'changes' => $changes];
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->isAudited($entity)) {
                // The id is gone after the flush: remember it now.
                $this->pending[] = ['action' => AuditLog::ACTION_DELETE, 'entity' => $entity, 'id' => $this->audit->identifier($entity), 'changes' => []];
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $entry) {
            $this->audit->write($entry['action'], $entry['entity'], $entry['id'], [] === $entry['changes'] ? null : $entry['changes']);
        }
    }

    private function isAudited(object $entity): bool
    {
        foreach (self::AUDITED as $class) {
            if ($entity instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $changeSet
     *
     * @return array<string, mixed>
     */
    private function changes(array $changeSet): array
    {
        $changeSet = array_diff_key($changeSet, array_flip(self::IGNORED_FIELDS));

        // Values loaded from the database and set again unchanged (e.g. JSON arrays) are not changes.
        $changeSet = array_filter($changeSet, static fn (mixed $change): bool => !\is_array($change) || !\array_key_exists(0, $change) || !\array_key_exists(1, $change) || $change[0] != $change[1]);

        return $this->audit->normalizeChanges($changeSet);
    }
}
