<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\RecordHeartbeat;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

/**
 * The worker records a heartbeat when it starts, while it runs (at most once a minute) and from the
 * scheduler every 5 minutes (src/Schedule.php); /health/worker reports a dead worker or scheduler.
 */
#[AsMessageHandler]
class Heartbeat
{
    private const KEY = 'app.worker_heartbeat';
    private const THROTTLE_SECONDS = 60;

    private int $lastWrite = 0;

    public function __construct(
        private readonly CacheItemPoolInterface $monitoringCache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordHeartbeat $message): void
    {
        $this->beat();
    }

    #[AsEventListener]
    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $this->beat();
    }

    #[AsEventListener]
    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->clock->now()->getTimestamp() - $this->lastWrite >= self::THROTTLE_SECONDS) {
            $this->beat();
        }
    }

    public function getLastBeat(): ?\DateTimeImmutable
    {
        $item = $this->monitoringCache->getItem(self::KEY);

        return $item->isHit() && \is_int($item->get()) ? (new \DateTimeImmutable())->setTimestamp($item->get()) : null;
    }

    private function beat(): void
    {
        $now = $this->clock->now()->getTimestamp();
        $item = $this->monitoringCache->getItem(self::KEY);
        $item->set($now);
        $item->expiresAfter(86400);
        $this->monitoringCache->save($item);
        $this->lastWrite = $now;
    }
}
