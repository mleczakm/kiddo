<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Ensures a fresh database connection before message handling in Swoole workers.
 * This prevents transaction errors caused by corrupted connection state.
 *
 * Only applies in Swoole task workers to avoid breaking nested transactions
 * in regular requests or tests.
 */
final readonly class DoctrineConnectionResetMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Connection $connection,
        private TaskWorkerContextInterface $taskWorkerContext
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Only reset connection in Swoole task workers
        if ($this->taskWorkerContext->isInTaskWorker() && $this->connection->isConnected()) {
            $this->connection->close();
            // Reconnect to ensure a fresh connection for the transaction middleware
            $this->connection->connect();
        }

        return $stack->next()
            ->handle($envelope, $stack);
    }
}
