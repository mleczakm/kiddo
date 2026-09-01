<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Healthcheck;

use App\Infrastructure\Healthcheck\SchedulerHeartbeatHealthcheck;
use App\Infrastructure\Scheduler\SchedulerHeartbeat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Clock\MockClock;

#[Group('unit')]
final class SchedulerHeartbeatHealthcheckTest extends TestCase
{
    private MockClock $clock;

    private SchedulerHeartbeat $heartbeat;

    #[\Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-09-01 12:00:00');
        $this->heartbeat = new SchedulerHeartbeat(
            new Psr16Cache(new ArrayAdapter()),
            $this->clock,
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testPassesButFlagsWhenNoTickRecordedYet(): void
    {
        $response = new SchedulerHeartbeatHealthcheck($this->heartbeat, 90)->check();

        static::assertTrue($response->getResult());
        static::assertSame('scheduler_heartbeat', $response->getName());
        static::assertNull($response->getParams()['seconds_since_last_tick']);
    }

    public function testPassesWhileTicksAreRecent(): void
    {
        $this->heartbeat->beat();
        $this->clock->sleep(30);

        $response = new SchedulerHeartbeatHealthcheck($this->heartbeat, 90)->check();

        static::assertTrue($response->getResult());
        static::assertSame(30, $response->getParams()['seconds_since_last_tick']);
        static::assertStringContainsString('ticking', $response->getMessage());
    }

    public function testFailsWhenTheLastTickIsOlderThanTheThreshold(): void
    {
        $this->heartbeat->beat();
        $this->clock->sleep(120);

        $response = new SchedulerHeartbeatHealthcheck($this->heartbeat, 90)->check();

        static::assertFalse($response->getResult());
        static::assertStringContainsString('exceeds threshold 90', $response->getMessage());
        static::assertSame(120, $response->getParams()['seconds_since_last_tick']);
    }
}
