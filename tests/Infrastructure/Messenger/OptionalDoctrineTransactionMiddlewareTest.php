<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Application\Command\ImportTransfersFromMail;
use App\Infrastructure\Messenger\OptionalDoctrineTransactionMiddleware;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

#[Group('unit')]
final class OptionalDoctrineTransactionMiddlewareTest extends TestCase
{
    private Connection&MockObject $connection;

    private EntityManagerInterface&MockObject $entityManager;

    private OptionalDoctrineTransactionMiddleware $middleware;

    #[\Override]
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new OptionalDoctrineTransactionMiddleware($registry);
    }

    public function testMessageRunningOutsideTransactionIsHandledWithoutOpeningOne(): void
    {
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->connection->expects($this->never())->method('commit');
        $this->entityManager->expects($this->never())->method('flush');

        $handler = new RecordingMiddleware();
        new MessageBus([$this->middleware, $handler])->dispatch(new ImportTransfersFromMail());

        static::assertSame(1, $handler->calls, 'The rest of the middleware stack must still run');
    }

    public function testOrdinaryMessageIsStillWrappedInTransaction(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('flush');
        $this->connection->expects($this->once())->method('commit');

        $handler = new RecordingMiddleware();
        new MessageBus([$this->middleware, $handler])->dispatch(new \stdClass());

        static::assertSame(1, $handler->calls);
    }

    public function testOrdinaryMessageIsRolledBackWhenHandlerFails(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        new MessageBus([$this->middleware, new RecordingMiddleware(new \RuntimeException('handler failed'))])->dispatch(
            new \stdClass(),
        );
    }
}

final class RecordingMiddleware implements MiddlewareInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ?\Throwable $failure = null,
    ) {}

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        ++$this->calls;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $envelope;
    }
}
