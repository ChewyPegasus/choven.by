<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\RecordHeartbeat;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The worker records a heartbeat every 5 minutes (src/Schedule.php); /health reports a dead worker.
 */
#[AsMessageHandler]
class Heartbeat
{
    private const KEY = 'app.worker_heartbeat';

    public function __construct(
        private readonly CacheItemPoolInterface $monitoringCache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordHeartbeat $message): void
    {
        $item = $this->monitoringCache->getItem(self::KEY);
        $item->set($this->clock->now()->getTimestamp());
        $item->expiresAfter(86400);
        $this->monitoringCache->save($item);
    }

    public function getLastBeat(): ?\DateTimeImmutable
    {
        $item = $this->monitoringCache->getItem(self::KEY);

        return $item->isHit() && \is_int($item->get()) ? (new \DateTimeImmutable())->setTimestamp($item->get()) : null;
    }
}
