<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use Swoole\Server;
use Swoole\Server\Task;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;

final readonly class TaskWorkerConnectionEnsuringHandler implements TaskHandler
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
        private TaskHandler $innerHandler,
    ) {}

    #[\Override]
    public function handle(Server $server, Task $task): void
    {
        $this->connectionEnsurer->ensureConnection();
        $this->innerHandler->handle($server, $task);
    }
}
