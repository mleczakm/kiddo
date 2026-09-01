<?php

declare(strict_types=1);

namespace App\Infrastructure\Healthcheck;

use App\Infrastructure\System\ProcResourceUsageProbe;
use SymfonyHealthCheckBundle\Check\CheckInterface;
use SymfonyHealthCheckBundle\Dto\Response;

/**
 * Puts this container's resident memory - total across all processes and the worst single
 * process - plus open-fd and TCP-socket counters into /health's JSON body, so a slow leak
 * is visible in `docker inspect` and in the deploy's health poll with no external metrics
 * pipeline needed (`docker stats` reports 0 B on the production LXC host, so per-process
 * /proc is the only source - see {@see \App\Infrastructure\System\ResourceUsageSnapshot}).
 *
 * Production keeps reaching a state where memory climbs and outbound connections (e.g.
 * outgoing mail) start failing while every other check still reports healthy and nothing
 * restarts the container. The RSS threshold turns a genuine runaway into an "unhealthy"
 * verdict that autoheal acts on; the fd/socket numbers never fail the check on their own -
 * they are here to tell a memory leak apart from an fd/socket leak after the fact.
 * A companion time series (Sentry metrics + app log) is emitted every minute by
 * {@see \App\Application\CommandHandler\SampleResourceUsageHandler}.
 */
final readonly class MemoryUsageHealthcheck implements CheckInterface
{
    /**
     * @param int $maxRssMib total resident set size across every process in this container's
     *                       PID namespace, in MiB, above which the check reports failure;
     *                       0 disables the failure and the check only ever reports the numbers
     */
    public function __construct(
        private ProcResourceUsageProbe $probe,
        private int $maxRssMib,
    ) {}

    #[\Override]
    public function check(): Response
    {
        try {
            $snapshot = $this->probe->capture();
        } catch (\Throwable $e) {
            return new Response('memory_usage', false, 'Resource usage probe failed: ' . $e->getMessage());
        }

        $params = $snapshot->toArray() + ['threshold_mib' => $this->maxRssMib];

        if ($this->maxRssMib > 0 && $snapshot->totalRssMib() > $this->maxRssMib) {
            return new Response(
                'memory_usage',
                false,
                sprintf('Resident memory %d MiB exceeds threshold %d MiB', $snapshot->totalRssMib(), $this->maxRssMib),
                $params,
            );
        }

        return new Response('memory_usage', true, 'Memory usage is healthy', $params);
    }
}
