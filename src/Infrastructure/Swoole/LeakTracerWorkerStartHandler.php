<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;

/**
 * Temporary, dev-only: same tracer check as LeakTracerRequestHandler, run once
 * a worker boots so leaks accumulating across worker restarts are visible too.
 */
final readonly class LeakTracerWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(
        private WorkerStartHandler $innerHandler,
    ) {}

    public function handle(Server $worker, int $workerId): void
    {
        $this->innerHandler->handle($worker, $workerId);

        if (\function_exists('swoole_tracer_leak_detect')) {
            swoole_tracer_leak_detect();
        }
    }
}
