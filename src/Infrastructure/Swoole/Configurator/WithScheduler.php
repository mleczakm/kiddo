<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Symfony\Scheduler;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use Symfony\Component\Cache\LockRegistry;
use Throwable;

final class WithScheduler implements Configurator
{
    private int $tickId;

    private bool $running = false;

    public function __construct(
        private readonly Scheduler $scheduler,
        // Every other request/message boundary in the app (HTTP requests via
        // ContextReleasingHttpKernelRequestHandler, async messages via
        // ContextReleasingTransportHandler) calls this to reset all pooled stateful
        // services when the coroutine ends. Timer::tick's coroutine is created directly by
        // Swoole, bypassing both of those, so nothing was ever resetting for it - see tick().
        private readonly CoWrapper $coWrapper,
        private readonly LoggerInterface $logger,
    ) {}

    public function __destruct()
    {
        if (isset($this->tickId)) {
            Timer::clear($this->tickId);
        }
    }

    #[\Override]
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
            if (!isset($this->tickId)) {
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
            // Registers this coroutine to release/reset every pooled stateful service it
            // touches when it ends - the same mechanism ContextReleasingHttpKernelRequestHandler
            // and ContextReleasingTransportHandler use for HTTP requests and async messages.
            // Without this, nothing pooled (Doctrine's entity manager, event dispatcher, etc.)
            // that scheduler->run() uses was ever being reset between ticks.
            //
            // Guarded by getCid(): moving this inside the try (previous fix) turned out not
            // to be enough on its own. configure() runs, and Timer::tick registers, exactly
            // once, in the master process (verified directly: logged the pid from both -
            // configure() logged once; every routine tick logged the same master pid). But
            // during Server::reload() - confirmed via direct reproduction, both from dev's
            // HMR file watcher and from forcing worker_max_request recycles under load - this
            // same timer *also* fires once in the manager process while it's mid-reload, with
            // no coroutine underneath it (Coroutine::getCid() === -1 there). defer() throws
            // Swoole\Error ("API must be called in the coroutine") in that spillover call in
            // a way that bypasses this method's own catch below entirely and kills the
            // manager process outright, despite sitting inside this try block. getCid() is
            // documented safe to call from any context (returns -1 instead of throwing), so
            // checking first avoids ever making the call that can't be reliably caught -
            // skipping pooled-service reset for one tick is a far smaller cost than that.
            // Reproduced this exact spillover live (pid=manager, cid=-1) and confirmed the
            // guard eliminates the crash: 30+ forced worker_max_request recycles under load,
            // zero crashes, server stayed healthy throughout.
            if (Coroutine::getCid() === -1) {
                $this->logger->warning(
                    'Scheduler tick has no coroutine context (likely mid worker reload/recycle) - skipping pooled-service reset for this tick',
                );
            } else {
                $this->coWrapper->defer();
            }

            $this->scheduler->run();
        } catch (Throwable $e) {
            // Timer::tick has no caller to propagate an exception to - nothing catches it,
            // so PHP's default uncaught-exception handling kicks in, which under Symfony's
            // ErrorHandler + Swoole's coroutine hooking becomes an uncaught
            // Swoole\ExitException that crashes the *entire process*, not just this
            // coroutine. Confirmed via direct test: stopping the database (so
            // ConnectionEnsurer's retry loop exhausts and rethrows) crashed the whole
            // container in a rapid restart loop - ~9 restarts within 30 seconds - purely
            // from this tick, with no task handler involved at all. A single failed tick
            // must not be allowed to bring the whole server down; log it and let the next
            // tick retry in a second.
            $this->logger->error('Scheduler tick failed', [
                'exception' => $e,
            ]);
        } finally {
            $this->running = false;
        }
    }
}
