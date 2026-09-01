<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Scheduler\SchedulerHeartbeat;
use App\Infrastructure\Symfony\Scheduler;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use Symfony\Component\Cache\LockRegistry;
use Symfony\Component\Lock\LockFactory;
use Throwable;

final class WithScheduler implements Configurator
{
    private const string LOCK_RESOURCE = 'app-scheduler-tick';

    private int $tickId;

    private bool $running = false;

    /**
     * Bumped on every tick start, and again by the watchdog if it force-releases a tick.
     * tick()'s finally only cleans up when this still matches the value its own tick took,
     * so a run() that returns after the watchdog already fired doesn't double-release the
     * lock or clobber a newer tick.
     */
    private int $tickGeneration = 0;

    public function __construct(
        private readonly Scheduler $scheduler,
        // Every other request/message boundary in the app (HTTP requests via
        // ContextReleasingHttpKernelRequestHandler, async messages via
        // ContextReleasingTransportHandler) calls this to reset all pooled stateful
        // services when the coroutine ends. Timer::tick's coroutine is created directly by
        // Swoole, bypassing both of those, so nothing was ever resetting for it - see tick().
        private readonly CoWrapper $coWrapper,
        private readonly LoggerInterface $logger,
        // $running below only guards reentrancy within *this* process. The "no coroutine
        // context" spillover tick documented in tick() runs in a genuinely separate OS
        // process (Swoole's manager, forked from master) with its own copy of this object -
        // a different $running instance, unreachable by the master's. Confirmed in
        // production: that spillover was firing on effectively every tick, not just during
        // a reload, so every due command (and every notification email it sends) was
        // dispatched twice a few hundred microseconds apart. The lock uses a kernel-level
        // semaphore (framework.lock: semaphore), visible to both processes, to actually
        // serialize them.
        private readonly LockFactory $lockFactory,
        // Written after every successful run() so SchedulerHeartbeatHealthcheck can tell,
        // from the HTTP worker, whether ticks are still completing.
        private readonly SchedulerHeartbeat $heartbeat,
        // Deadline for one Scheduler::run() pass, enforced by the Timer::after watchdog in
        // tick(). Kept well under SchedulerHeartbeatHealthcheck's threshold so a single slow
        // tick self-heals (the next tick just proceeds) without ever tripping the check.
        private readonly int $tickTimeoutSeconds = 15,
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

        // Serializes this tick against the same tick firing concurrently in another OS
        // process (see the constructor doc on $lockFactory) - the $running flag above only
        // ever protects against overlap within this process.
        $lock = $this->lockFactory->createLock(self::LOCK_RESOURCE);

        try {
            if (!$lock->acquire()) {
                return;
            }
        } catch (Throwable $e) {
            // Same reasoning as the outer catch below: a broken lock backend must not crash
            // the process, just skip this tick and let the next one retry.
            $this->logger->error('Scheduler tick lock acquisition failed', [
                'exception' => $e,
            ]);

            return;
        }

        $this->running = true;
        $generation = ++$this->tickGeneration;

        // A SysV semaphore (framework.lock: semaphore) is NOT released when the coroutine
        // holding it hangs, so a Scheduler::run() that never returns - seen repeatedly in
        // production, e.g. a bare executeQuery('SELECT 1') that just never comes back - would
        // keep $running true and the lock held forever, and every later tick would either
        // short-circuit on $running or fail to acquire. This watchdog fires once after the
        // deadline: if this is still the current tick and still running, it drops $running
        // and releases the lock so the next Timer::tick fire can proceed. run() itself is
        // left to finish (or not) on its own - bumping the generation makes tick()'s finally
        // a no-op for it, so there's no double release. A persistently hung run() then just
        // burns one wasted tick per deadline until SchedulerHeartbeatHealthcheck -> autoheal
        // restarts the container. Deliberately a separate Timer rather than running run() in
        // a child coroutine we can walk away from: nested Coroutine::create + Channel::pop
        // under PHPUnit + Xdebug coverage segfaults the process on shutdown.
        $watchdogId = Timer::after($this->tickTimeoutSeconds * 1000, function () use ($generation, $lock): void {
            // A Timer::after callback has no caller either, so it swallows the same way tick()
            // does below - an uncaught throw here would take the whole process down.
            try {
                if (!$this->running || $this->tickGeneration !== $generation) {
                    return;
                }

                ++$this->tickGeneration;
                $this->running = false;
                $lock->release();

                $this->logger->error('Scheduler tick exceeded its timeout; force-released the lock so scheduling can resume', [
                    'timeout_seconds' => $this->tickTimeoutSeconds,
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Scheduler watchdog failed', ['exception' => $e]);
            }
        });

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
            $this->heartbeat->beat();
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
            Timer::clear($watchdogId);

            // Skip cleanup if the watchdog already force-released this tick (it bumps the
            // generation) or a newer tick has since taken over - releasing again would
            // over-count the semaphore or free a lock this object no longer owns.
            if ($this->tickGeneration === $generation) {
                $this->running = false;
                $lock->release();
            }
        }
    }
}
