<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\SchedulerConnectionResetter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Event\PreRunEvent;

#[Group('unit')]
class SchedulerConnectionResetterTest extends TestCase
{
    public function testOnPreRunEnsuresConnectionIsConnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        $resetter = new SchedulerConnectionResetter($connection);
        $event = $this->createMock(PreRunEvent::class);

        $resetter->onPreRun($event);
    }

    public function testOnPreRunPingsExistingConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        $resetter = new SchedulerConnectionResetter($connection);
        $event = $this->createMock(PreRunEvent::class);

        $resetter->onPreRun($event);
    }

    public function testOnPreRunRetriesOnConnectionFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('isConnected')
            ->willReturn(true, true);
        $connection->expects($this->exactly(3))
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturnCallback(function () {
                static $calls = 0;
                ++$calls;

                return match ($calls) {
                    1, 2 => throw new Exception('no connection to the server'),
                    default => $this->createMock(Result::class),
                };
            });
        $connection->expects($this->exactly(2))
            ->method('close');

        $resetter = new SchedulerConnectionResetter($connection);
        $event = $this->createMock(PreRunEvent::class);

        $resetter->onPreRun($event);
    }

    public function testOnPreRunThrowsExceptionAfterMaxRetries(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no connection to the server');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('isConnected')
            ->willReturn(true, true);
        $connection->expects($this->exactly(3))
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturnCallback(function (): void {
                throw new Exception('no connection to the server');
            });
        $connection->expects($this->exactly(2))
            ->method('close');

        $resetter = new SchedulerConnectionResetter($connection);
        $event = $this->createMock(PreRunEvent::class);

        $resetter->onPreRun($event);
    }
}
