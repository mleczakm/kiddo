<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\CacheConnectionEnsurer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CacheConnectionEnsurerTest extends TestCase
{
    public function testEnsureConnectionPingsDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('isTransactionActive')
            ->willReturn(false);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        new CacheConnectionEnsurer($connection)
            ->ensureConnection();
    }

    public function testEnsureConnectionDiscardsActiveTransactionBeforePing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('isTransactionActive')
            ->willReturn(true);
        $connection->expects($this->once())
            ->method('rollBack');
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        new CacheConnectionEnsurer($connection)
            ->ensureConnection();
    }

    public function testEnsureConnectionRetriesOnConnectionFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('isTransactionActive')
            ->willReturn(false);
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
                    1, 2 => throw new ConnectionException('no connection to the server'),
                    default => $this->createMock(Result::class),
                };
            });
        $connection->expects($this->exactly(2))
            ->method('close');

        new CacheConnectionEnsurer($connection)
            ->ensureConnection();
    }

    public function testEnsureConnectionThrowsExceptionAfterMaxRetries(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no connection to the server');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('isTransactionActive')
            ->willReturn(false);
        $connection->expects($this->exactly(2))
            ->method('isConnected')
            ->willReturn(true, true);
        $connection->expects($this->exactly(3))
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturnCallback(function (): void {
                throw new ConnectionException('no connection to the server');
            });
        $connection->expects($this->exactly(2))
            ->method('close');

        new CacheConnectionEnsurer($connection)
            ->ensureConnection();
    }
}
