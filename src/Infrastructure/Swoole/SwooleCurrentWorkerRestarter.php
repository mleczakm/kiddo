<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

final readonly class SwooleCurrentWorkerRestarter implements CurrentWorkerRestarterInterface
{
    public function __construct(
        private SwooleServerProviderInterface $serverProvider,
    ) {}

    public function restart(): void
    {
        try {
            $server = $this->serverProvider->getServer();
            $server->stop($server->worker_id);
        } catch (\Throwable) {
            // Not running inside a Swoole worker (e.g. CLI or tests).
        }
    }
}
