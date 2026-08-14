<?php

declare(strict_types=1);

namespace App\Infrastructure\Healthcheck;

use SymfonyHealthCheckBundle\Check\CheckInterface;
use SymfonyHealthCheckBundle\Dto\Response;

final readonly class ProcessCountHealthcheck implements CheckInterface
{
    public function __construct(
        private int $maxProcesses,
    ) {}

    /**
     * Steady state is ~5 processes (master, manager, 1 HTTP worker, 2 task workers - see
     * config/packages/swoole.yaml). Three separate production incidents (worker-restart races
     * in AliorNotificationMailProvider and the old WorkerRestartingTaskHandler, and an
     * unprotected CoWrapper::defer() call in WithScheduler) each manifested as this
     * container's process count climbing into the dozens to low thousands - observed directly
     * as ~20 tiny (~5.4MiB) leaked processes on one occurrence and ~1030 on another - rather
     * than any single request failing outright, so /health's other checks kept reporting
     * healthy throughout both incidents. Counting /proc entries (only this container's PID
     * namespace, not the host's) catches that pattern directly instead of waiting for it to
     * eventually produce a symptom another check would notice.
     */
    public function check(): Response
    {
        $count = count(glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: []);

        if ($count > $this->maxProcesses) {
            return new Response(
                'process_count',
                false,
                sprintf('Process count %d exceeds threshold %d', $count, $this->maxProcesses),
                [
                    'count' => $count,
                    'threshold' => $this->maxProcesses,
                ]
            );
        }

        return new Response('process_count', true, 'Process count is healthy', [
            'count' => $count,
            'threshold' => $this->maxProcesses,
        ]);
    }
}
