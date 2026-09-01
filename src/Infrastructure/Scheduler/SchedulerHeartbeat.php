<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * One shared timestamp: "when did the scheduler tick last complete a full pass".
 *
 * Written by {@see \App\Infrastructure\Swoole\Configurator\WithScheduler} after every
 * successful `Scheduler::run()`, read by {@see \App\Infrastructure\Healthcheck\SchedulerHeartbeatHealthcheck}
 * from the HTTP worker. Backed by the DBAL `cache` pool (see config/packages/cache.yaml),
 * the same cross-process, deploy-surviving store {@see \App\Infrastructure\Healthcheck\DoctrineInsideTaskWorkerHealthcheck}
 * relies on.
 *
 * Production has hit a state where the `Timer::tick` coroutine wedges inside
 * `Scheduler::run()` holding the `app-scheduler-tick` semaphore forever: no scheduled task
 * (transfer import, reminder mail, payment expiry) runs again, yet every /health check
 * still passes and the container is never restarted. A stale heartbeat is the direct
 * signal for that.
 */
final readonly class SchedulerHeartbeat
{
    private const string CACHE_KEY = 'scheduler_last_tick_at';

    /**
     * Long enough that a wedged scheduler keeps producing an ever-growing "seconds since"
     * for a full day rather than the key silently expiring back to "unknown".
     */
    private const int TTL_SECONDS = 86_400;

    public function __construct(
        private CacheInterface $cache,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * Best-effort: a flaky cache write must not turn a healthy tick into a "tick failed"
     * error, so this swallows and logs rather than throwing.
     */
    public function beat(): void
    {
        try {
            $this->cache->set(self::CACHE_KEY, $this->clock->now()->getTimestamp(), self::TTL_SECONDS);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to write scheduler heartbeat', ['exception' => $e]);
        }
    }

    /**
     * Seconds since the last recorded tick, or null when it is not knowable - none recorded
     * yet (fresh boot), the key expired (scheduler dead longer than the TTL), or the cache
     * read itself failed.
     */
    public function secondsSinceLastBeat(): ?int
    {
        try {
            $lastTickAt = $this->cache->get(self::CACHE_KEY);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to read scheduler heartbeat', ['exception' => $e]);

            return null;
        }

        if (!is_int($lastTickAt)) {
            return null;
        }

        return max(0, $this->clock->now()->getTimestamp() - $lastTickAt);
    }
}
