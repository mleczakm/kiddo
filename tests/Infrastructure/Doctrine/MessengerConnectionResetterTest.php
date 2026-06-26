<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\MessengerConnectionResetter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

#[Group('unit')]
class MessengerConnectionResetterTest extends TestCase
{
    public function testOnWorkerMessageReceivedEnsuresConnectionIsConnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('isConnected')
            ->willReturn(false);
        $connection->expects($this->once())
            ->method('connect');
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        $resetter = new MessengerConnectionResetter($connection);
        $event = new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'async');

        $resetter->onWorkerMessageReceived($event);
    }

    public function testOnWorkerMessageReceivedPingsExistingConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);
        $connection->expects($this->never())
            ->method('connect');
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        $resetter = new MessengerConnectionResetter($connection);
        $event = new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'async');

        $resetter->onWorkerMessageReceived($event);
    }

    public function testOnWorkerMessageReceivedRetriesOnConnectionFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(5))
            ->method('isConnected')
            ->willReturn(false, true, false, true, false);
        $connection->expects($this->exactly(3))
            ->method('connect');
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

        $resetter = new MessengerConnectionResetter($connection);
        $event = new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'async');

        $resetter->onWorkerMessageReceived($event);
    }

    public function testOnWorkerMessageReceivedThrowsExceptionAfterMaxRetries(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no connection to the server');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(5))
            ->method('isConnected')
            ->willReturn(false, true, false, true, false);
        $connection->expects($this->exactly(3))
            ->method('connect');
        $connection->expects($this->exactly(3))
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturnCallback(function (): void {
                throw new Exception('no connection to the server');
            });
        $connection->expects($this->exactly(2))
            ->method('close');

        $resetter = new MessengerConnectionResetter($connection);
        $event = new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'async');

        $resetter->onWorkerMessageReceived($event);
    }
}
