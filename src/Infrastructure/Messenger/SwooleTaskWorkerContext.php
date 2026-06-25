<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Infrastructure\Swoole\SwooleServerProviderInterface;

final readonly class SwooleTaskWorkerContext implements TaskWorkerContextInterface
{
    public function __construct(
        private ?SwooleServerProviderInterface $serverProvider = null,
    ) {}

    public function isInTaskWorker(): bool
    {
        if (! extension_loaded('swoole') || $this->serverProvider === null) {
            return false;
        }

        try {
            return $this->serverProvider->getServer()
                ->taskworker;
        } catch (\Throwable) {
            return false;
        }
    }
}
