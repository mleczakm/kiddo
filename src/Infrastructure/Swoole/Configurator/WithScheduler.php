<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Symfony\Scheduler;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use Symfony\Component\Cache\LockRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WithScheduler implements Configurator
{
    private int $tickId;

    private bool $running = false;

    public function __construct(
        private readonly Scheduler $scheduler,
        // Exempted from per-coroutine pooling (services.yaml) so this is always the same
        // shared instance, safe to inject and reset directly.
        #[Autowire(service: 'doctrine.debug_data_holder')]
        private readonly BacktraceDebugDataHolder $debugDataHolder,
    ) {}

    public function __destruct()
    {
        if (isset($this->tickId)) {
            Timer::clear($this->tickId);
        }
    }

    public function configure(Server $server): void
    {
        // Symfony Cache's LockRegistry protects cache stampedes with a pool of ~20 flock()
        // locks on vendor PHP files, and Scheduler's Checkpoint::save() forces every tick
        // through it (beta=INF). Verified by direct A/B test: with this call removed, the
        // scheduler's Timer::tick coroutine reliably wedged forever after ~6-8 ticks (a bare
        // executeQuery('SELECT 1') in ConnectionEnsurer never returning, no error, no
        // concurrency involved); with it in place, 370+ consecutive ticks over ~6 minutes
        // with zero gaps. The exact mechanism isn't fully nailed down - the lock guards a
        // DB write to the "cache" connection, a different connection than the one observed
        // hung, so there may be a secondary effect (e.g. a stuck lock-holding coroutine
        // starving others) rather than a direct dependency. LockRegistry's cross-process
        // stampede protection is also pointless here anyway: this master is the only process
        // ever writing this checkpoint key.
        LockRegistry::setFiles([]);

        $this->tickId = Timer::tick(1000, $this->tick(...));

        $server->on('shutdown', function (): void {
            if (! isset($this->tickId)) {
                return;
            }
            Timer::clear($this->tickId);
        });
    }

    public function tick(): void
    {
        // Defense in depth: Timer::tick fires a new coroutine every second regardless of
        // whether the previous tick finished. If scheduler->run() ever stalls again for
        // any reason, this keeps overlapping ticks from piling up unboundedly instead of
        // just skipping until the stuck one clears.
        if ($this->running) {
            return;
        }

        $this->running = true;

        try {
            $this->scheduler->run();

            // This tick never goes through kernel.terminate, so nothing normally resets
            // request-scoped accumulators for it. Doctrine's query-debug middleware appends
            // one entry per query regardless of debug mode, unbounded without this — confirmed
            // via memory_get_usage before/after isolating this exact call: ~6KB/tick from the
            // two connection-health-check queries this tick already runs every second.
            $this->debugDataHolder->reset();
        } finally {
            $this->running = false;
        }
    }
}
