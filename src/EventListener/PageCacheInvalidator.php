<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Extra;
use App\Entity\FaqItem;
use App\Entity\Post;
use App\Entity\RaftingRoute;
use App\Entity\Review;
use App\Entity\Setting;
use App\Entity\Tour;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Empties the full-page cache when something shown on public pages changes
 * (tours, routes, reviews, blog, FAQ, add-ons, site settings).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class PageCacheInvalidator
{
    private const PUBLIC_CONTENT = [Tour::class, RaftingRoute::class, Review::class, Setting::class, Post::class, FaqItem::class, Extra::class];

    private bool $dirty = false;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.share_dir%/http_cache')]
        private readonly string $cacheDir,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ([$uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates(), $uow->getScheduledEntityDeletions()] as $entities) {
            foreach ($entities as $entity) {
                foreach (self::PUBLIC_CONTENT as $class) {
                    if ($entity instanceof $class) {
                        $this->dirty = true;

                        return;
                    }
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->dirty) {
            return;
        }
        $this->dirty = false;

        $this->purge();
    }

    public function purge(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        try {
            // Move the store away first, so requests running now never read a half-deleted cache.
            $trash = $this->cacheDir.'.old.'.bin2hex(random_bytes(4));
            $this->filesystem->rename($this->cacheDir, $trash);
            $this->filesystem->remove($trash);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not empty the page cache: {message}', ['message' => $e->getMessage()]);
        }
    }
}
