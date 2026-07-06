<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Infrastructure\Messenger\DoctrineConnectionResetMiddleware;
use App\Infrastructure\Messenger\TaskWorkerContextInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

#[Group('unit')]
final class DoctrineConnectionResetMiddlewareTest extends MiddlewareTestCase
{
    public function testClosesConnectionWhenConnectedInTaskWorker(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))
            ->method('isConnected')
            ->willReturn(true);
        $connection->expects(self::exactly(2))
            ->method('close');
        $connection->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT 1');

        $taskWorkerContext = $this->createMock(TaskWorkerContextInterface::class);
        $taskWorkerContext->expects(self::exactly(2))
            ->method('isInTaskWorker')
            ->willReturn(true);

        $middleware = new DoctrineConnectionResetMiddleware($connection, $taskWorkerContext);
        $envelope = new Envelope(new \stdClass());

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testDoesNotCloseConnectionWhenNotInTaskWorker(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())
            ->method('isConnected');
        $connection->expects(self::never())
            ->method('close');

        $taskWorkerContext = $this->createMock(TaskWorkerContextInterface::class);
        $taskWorkerContext->expects(self::once())
            ->method('isInTaskWorker')
            ->willReturn(false);

        $middleware = new DoctrineConnectionResetMiddleware($connection, $taskWorkerContext);
        $envelope = new Envelope(new \stdClass());

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testDoesNotCloseConnectionWhenNotConnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('isConnected')
            ->willReturn(false);
        $connection->expects(self::never())
            ->method('close');

        $taskWorkerContext = $this->createMock(TaskWorkerContextInterface::class);
        $taskWorkerContext->expects(self::once())
            ->method('isInTaskWorker')
            ->willReturn(true);

        $middleware = new DoctrineConnectionResetMiddleware($connection, $taskWorkerContext);
        $envelope = new Envelope(new \stdClass());

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testCallsNextMiddlewareInStack(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')
            ->willReturn(true);
        $connection->method('close');

        $taskWorkerContext = $this->createMock(TaskWorkerContextInterface::class);
        $taskWorkerContext->method('isInTaskWorker')
            ->willReturn(false);

        $middleware = new DoctrineConnectionResetMiddleware($connection, $taskWorkerContext);
        $envelope = new Envelope(new \stdClass());

        $capture = new \stdClass();
        $capture->called = false;

        $next = new readonly class ($capture) implements MiddlewareInterface {
            public function __construct(
                private \stdClass $capture
            ) {}

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->capture->called = true;
                return $envelope;
            }
        };

        $stack = new readonly class ($next) implements StackInterface {
            public function __construct(
                private MiddlewareInterface $next
            ) {}

            public function next(): MiddlewareInterface
            {
                return $this->next;
            }
        };

        $middleware->handle($envelope, $stack);

        self::assertTrue($capture->called);
    }
}
