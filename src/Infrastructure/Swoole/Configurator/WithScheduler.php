<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Symfony\Scheduler;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
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
        // shared instance, safe to inject and reset directly. It's exempt from pooling
        // specifically because it's exempt from CoWrapper's reset cycle too (see tick()) -
        // pooling and auto-reset are the same mechanism, opting out of one opts out of both.
        #[Autowire(service: 'doctrine.debug_data_holder')]
        private readonly BacktraceDebugDataHolder $debugDataHolder,
        // Every other request/message boundary in the app (HTTP requests via
        // ContextReleasingHttpKernelRequestHandler, async messages via
        // ContextReleasingTransportHandler) calls this to reset all pooled stateful
        // services when the coroutine ends. Timer::tick's coroutine is created directly by
        // Swoole, bypassing both of those, so nothing was ever resetting for it - see tick().
        private readonly CoWrapper $coWrapper,
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
        //
        // Deliberately global rather than scoped to just this pool via the "cleaner"-looking
        // AbstractAdapter::setCallbackWrapper(null): cache.app is wrapped by Sentry's cache
        // tracer (no setCallbackWrapper()) and its raw pool underneath is coroutine-pooled -
        // a fresh instance per coroutine - so a one-time setCallbackWrapper() call at boot
        // would only patch whichever instance is checked out at that moment, not the ones
        // future coroutines get. LockRegistry's static state doesn't have that problem. The
        // trade-off is losing stampede protection for every cache pool app-wide, not just
        // the scheduler's - acceptable since it only guards redundant recomputation on a
        // concurrent cache-miss race, not correctness.
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

        // Registers this coroutine to release/reset every pooled stateful service it touches
        // when it ends - the same mechanism ContextReleasingHttpKernelRequestHandler and
        // ContextReleasingTransportHandler use for HTTP requests and async messages. Without
        // this, nothing pooled (Doctrine's entity manager, event dispatcher, etc.) that
        // scheduler->run() uses was ever being reset between ticks.
        $this->coWrapper->defer();

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
