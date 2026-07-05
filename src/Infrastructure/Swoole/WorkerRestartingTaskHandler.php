<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Swoole\Server\Task;
use Psr\Log\LoggerInterface;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use Throwable;

final readonly class WorkerRestartingTaskHandler implements TaskHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private CurrentWorkerRestarterInterface $workerRestarter,
        private TaskHandler $innerHandler,
    ) {}

    public function handle(Server $server, Task $task): void
    {
        try {
            $this->innerHandler->handle($server, $task);
        } catch (Throwable $e) {
            $this->logger->critical(
                sprintf('Task worker exception: %s. Restarting worker.', $e->getMessage()),
                [
                    'exception' => $e,
                ]
            );

            // Restart the worker to recover from the error
            $this->workerRestarter->restart();
        }
    }
}
