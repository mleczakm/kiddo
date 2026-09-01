<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Scheduler;

use App\Infrastructure\Scheduler\SchedulerHeartbeat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Clock\MockClock;

#[Group('unit')]
final class SchedulerHeartbeatTest extends TestCase
{
    public function testReturnsNullWhenNoTickHasBeenRecorded(): void
    {
        $heartbeat = new SchedulerHeartbeat(
            new Psr16Cache(new ArrayAdapter()),
            new MockClock(),
            $this->createMock(LoggerInterface::class),
        );

        static::assertNull($heartbeat->secondsSinceLastBeat());
    }

    public function testMeasuresElapsedSecondsSinceTheLastBeat(): void
    {
        $clock = new MockClock('2026-09-01 12:00:00');
        $heartbeat = new SchedulerHeartbeat(
            new Psr16Cache(new ArrayAdapter()),
            $clock,
            $this->createMock(LoggerInterface::class),
        );

        $heartbeat->beat();
        static::assertSame(0, $heartbeat->secondsSinceLastBeat());

        $clock->sleep(47);
        static::assertSame(47, $heartbeat->secondsSinceLastBeat());
    }

    public function testBeatSwallowsAndLogsCacheFailures(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('set')->willThrowException(new \RuntimeException('cache down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('warning')
            ->with('Failed to write scheduler heartbeat', static::anything());

        $heartbeat = new SchedulerHeartbeat($cache, new MockClock(), $logger);

        $heartbeat->beat(); // must not throw
    }

    public function testSecondsSinceLastBeatReturnsNullWhenTheCacheReadFails(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willThrowException(new \RuntimeException('cache down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('warning')
            ->with('Failed to read scheduler heartbeat', static::anything());

        $heartbeat = new SchedulerHeartbeat($cache, new MockClock(), $logger);

        static::assertNull($heartbeat->secondsSinceLastBeat());
    }
}
