<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Psr\Log\LoggerInterface;
use Swoole\Server;
use Swoole\Server\Task;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use Throwable;

final readonly class ExceptionLoggingTaskHandler implements TaskHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private TaskHandler $innerHandler,
    ) {}

    public function handle(Server $server, Task $task): void
    {
        try {
            $this->innerHandler->handle($server, $task);
        } catch (Throwable $e) {
            // Deliberately doesn't restart the worker (previously called
            // CurrentWorkerRestarterInterface::restart(), i.e. $server->stop($server->worker_id),
            // now removed entirely). See AliorNotificationMailProvider's catch block for why:
            // that same call, triggered by a transient IMAP failure, raced against Swoole's own
            // process management in production, corrupting a task-worker slot - for hours
            // afterward roughly half of everything the scheduler dispatched was silently
            // dropped, eventually exhausting file descriptors and OOM-killing the container. A
            // single task failing doesn't need the whole worker torn down for it;
            // worker_max_request already recycles workers safely on a normal schedule.
            $this->logger->critical(sprintf('Task worker exception: %s', $e->getMessage()), [
                'exception' => $e,
            ]);
        }
    }
}
