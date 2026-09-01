<?php

declare(strict_types=1);

namespace App\Infrastructure\Healthcheck;

use App\Infrastructure\Scheduler\SchedulerHeartbeat;
use SymfonyHealthCheckBundle\Check\CheckInterface;
use SymfonyHealthCheckBundle\Dto\Response;

/**
 * Fails when the scheduler has not completed a tick within `maxSilenceSeconds`.
 *
 * The `Timer::tick` coroutine in {@see \App\Infrastructure\Swoole\Configurator\WithScheduler}
 * fires every second; production has repeatedly seen it wedge inside `Scheduler::run()` -
 * holding the `app-scheduler-tick` semaphore, `$running` stuck true - so that no scheduled
 * task (transfer import, reminder mail, payment/booking expiry) runs again while every other
 * /health check keeps passing and nothing restarts the container. Counting the seconds
 * since the last heartbeat catches that directly and, via the 503 the bundle returns for a
 * failed check, hands autoheal something to act on in ~90s instead of waiting hours for the
 * memory_usage threshold.
 */
final readonly class SchedulerHeartbeatHealthcheck implements CheckInterface
{
    /**
     * @param int $maxSilenceSeconds seconds without a completed tick above which the check
     *                               fails; the tick interval is 1s, so this is tens of
     *                               missed ticks, not a tight bound
     */
    public function __construct(
        private SchedulerHeartbeat $heartbeat,
        private int $maxSilenceSeconds,
    ) {}

    #[\Override]
    public function check(): Response
    {
        $secondsSinceLastTick = $this->heartbeat->secondsSinceLastBeat();

        if ($secondsSinceLastTick === null) {
            // No tick recorded yet (fresh boot) - a genuinely stuck boot is already covered
            // by the other checks, so this stays green rather than flapping during startup.
            return new Response('scheduler_heartbeat', true, 'No scheduler tick recorded yet', [
                'seconds_since_last_tick' => null,
                'threshold_seconds' => $this->maxSilenceSeconds,
            ]);
        }

        $params = [
            'seconds_since_last_tick' => $secondsSinceLastTick,
            'threshold_seconds' => $this->maxSilenceSeconds,
        ];

        if ($secondsSinceLastTick > $this->maxSilenceSeconds) {
            return new Response(
                'scheduler_heartbeat',
                false,
                sprintf(
                    'Scheduler last completed a tick %ds ago, exceeds threshold %ds',
                    $secondsSinceLastTick,
                    $this->maxSilenceSeconds,
                ),
                $params,
            );
        }

        return new Response('scheduler_heartbeat', true, 'Scheduler is ticking', $params);
    }
}
