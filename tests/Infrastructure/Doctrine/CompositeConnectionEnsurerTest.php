<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\CompositeConnectionEnsurer;
use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit')]
final class CompositeConnectionEnsurerTest extends TestCase
{
    public function testEnsureConnectionDelegatesToAllEnsurers(): void
    {
        $first = $this->createMock(ConnectionEnsurerInterface::class);
        $first->expects($this->once())->method('ensureConnection');

        $second = $this->createMock(ConnectionEnsurerInterface::class);
        $second->expects($this->once())->method('ensureConnection');

        new CompositeConnectionEnsurer([$first, $second])->ensureConnection();
    }

    public function testEnsureConnectionStopsAndPropagatesFirstFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('first failed');

        $first = $this->createMock(ConnectionEnsurerInterface::class);
        $first
            ->expects($this->once())
            ->method('ensureConnection')
            ->willThrowException(new RuntimeException('first failed'));

        $second = $this->createMock(ConnectionEnsurerInterface::class);
        $second->expects($this->never())->method('ensureConnection');

        new CompositeConnectionEnsurer([$first, $second])->ensureConnection();
    }
}
