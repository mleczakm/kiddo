<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Infrastructure\Messenger\SwooleTaskWorkerContext;
use App\Infrastructure\Swoole\SwooleServerProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Server;

#[Group('unit')]
final class SwooleTaskWorkerContextTest extends TestCase
{
    public function testReturnsFalseWhenServerProviderIsMissing(): void
    {
        $context = new SwooleTaskWorkerContext();

        self::assertFalse($context->isInTaskWorker());
    }

    public function testReturnsFalseWhenServerCannotBeResolved(): void
    {
        $provider = $this->createMock(SwooleServerProviderInterface::class);
        $provider->expects($this->once())
            ->method('getServer')
            ->willThrowException(new \RuntimeException('Server not initialized'));

        $context = new SwooleTaskWorkerContext($provider);

        self::assertFalse($context->isInTaskWorker());
    }

    public function testReturnsTaskWorkerFlagFromServer(): void
    {
        $server = $this->createMock(Server::class);
        $server->taskworker = true;

        $provider = $this->createMock(SwooleServerProviderInterface::class);
        $provider->expects($this->once())
            ->method('getServer')
            ->willReturn($server);

        $context = new SwooleTaskWorkerContext($provider);

        self::assertTrue($context->isInTaskWorker());
    }
}
