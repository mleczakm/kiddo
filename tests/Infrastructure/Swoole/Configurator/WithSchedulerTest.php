<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Scheduler\SchedulerHeartbeat;
use App\Infrastructure\Swoole\Configurator\WithScheduler;
use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\LockRegistry;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\MessageBusInterface;

use function Swoole\Coroutine\run;

#[Group('unit')]
final class WithSchedulerTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $originalLockRegistryFiles;

    #[\Override]
    protected function setUp(): void
    {
        $this->originalLockRegistryFiles = self::lockRegistryFiles();
    }

    #[\Override]
    protected function tearDown(): void
    {
        LockRegistry::setFiles($this->originalLockRegistryFiles);
    }

    public function testRegisterSwooleTick(): void
    {
        static::assertEmpty(iterator_to_array(Timer::list()));
        ($withScheduler = new WithScheduler(
            new Scheduler($this->createMock(MessageBusInterface::class), []),
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
            self::lockFactory(),
            self::heartbeat(),
        ))->configure($this->createMock(Server::class));

        $ticks = iterator_to_array(Timer::list());

        static::assertNotEmpty($ticks);

        $withScheduler->__destruct();

        static::assertEmpty(iterator_to_array(Timer::list()));
    }

    public function testConfigureDisablesLockRegistryFileLocking(): void
    {
        LockRegistry::setFiles(['some/file.php']);

        ($withScheduler = new WithScheduler(
            new Scheduler($this->createMock(MessageBusInterface::class), []),
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
            self::lockFactory(),
            self::heartbeat(),
        ))->configure($this->createMock(Server::class));

        static::assertSame([], self::lockRegistryFiles());

        $withScheduler->__destruct();
    }

    public function testTickRunsScheduler(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->once())->method('run');

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
            self::lockFactory(),
            self::heartbeat(),
        );

        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });
    }

    public function testTickReleasesPooledServicesForTheCurrentCoroutine(): void
    {
        // Real CoWrapper/ServicePoolContainer instead of mocks: both are final, and this is
        // exactly the wiring ContextReleasingHttpKernelRequestHandler and
        // ContextReleasingTransportHandler rely on for every HTTP request/async message, so
        // it's worth verifying tick() plugs into the real thing rather than just a stub.
        $pool = $this->createMock(ServicePool::class);
        $pool->expects($this->once())->method('releaseFromCoroutine');

        $coWrapper = new CoWrapper(new ServicePoolContainer([new ServicePoolEntry($pool)]));

        $withScheduler = new WithScheduler(
            $this->createMock(Scheduler::class),
            $coWrapper,
            $this->createMock(LoggerInterface::class),
            self::lockFactory(),
            self::heartbeat(),
        );

        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });
    }

    public function testTickSkipsWhileAlreadyRunning(): void
    {
        $scheduler = $this->createMock(Scheduler::class);

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
            self::lockFactory(),
            self::heartbeat(),
        );

        $scheduler
            ->expects($this->once())
            ->method('run')
            ->willReturnCallback(static function () use ($withScheduler): void {
                // Simulates an overlapping tick firing while this one is still in-flight.
                $withScheduler->tick();
            });

        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });
    }

    public function testTickResetsRunningFlagAfterExceptionSoLaterTicksAreNotSkipped(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $scheduler
            ->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(static function (): void {
                static $calls = 0;
                ++$calls;

                if ($calls === 1) {
                    throw new RuntimeException('boom');
                }
            });

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
            self::lockFactory(),
            self::heartbeat(),
        );

        // A failed tick must not propagate - Timer::tick has no caller to catch it, so an
        // uncaught exception here crashes the entire process (confirmed via direct test:
        // stopping the database rapidly crash-looped the whole container from this exact
        // path, ~9 restarts in 30 seconds, before this catch was added).
        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });

        // A stuck/failed tick must not permanently block future ticks from running.
        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });
    }

    public function testTickLogsAndSwallowsExceptionsFromScheduler(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $exception = new RuntimeException('boom');
        $scheduler->expects($this->once())->method('run')->willThrowException($exception);

        // Asserting via ->with() on a mock invoked from inside Swoole\Coroutine\run() crashes
        // PHPUnit 11's parameter-matcher (it walks the call stack for the enclosing TestCase,
        // which a coroutine's separate stack doesn't have) - capture the call instead and
        // assert on it from the outer, non-coroutine stack once run() returns.
        $loggedMessage = null;
        $loggedContext = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->willReturnCallback(static function (string $message, array $context) use (
                &$loggedMessage,
                &$loggedContext,
            ): void {
                $loggedMessage = $message;
                $loggedContext = $context;
            });

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            $logger,
            self::lockFactory(),
            self::heartbeat(),
        );

        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });

        static::assertSame('Scheduler tick failed', $loggedMessage);
        static::assertSame(
            [
                'exception' => $exception,
            ],
            $loggedContext,
        );
    }

    public function testTickSkipsPooledServiceResetAndLogsAWarningOutsideACoroutine(): void
    {
        // Reproduces the actual failure mode directly, without needing swoole's own reload/
        // recycle timing: calling tick() with no enclosing Coroutine\run() is exactly the
        // condition (Coroutine::getCid() === -1) that CoWrapper::defer() can't handle -
        // confirmed live in production and reproduced locally (a Server::reload() mid-tick
        // crashes the *manager* process, bypassing tick()'s own try/catch entirely). This
        // must not call defer() at all in that case, and must not crash this test process
        // doing it (a bare, unguarded defer() call here previously aborted the whole PHP
        // process, exit 255, not just this test).
        $pool = $this->createMock(ServicePool::class);
        $pool->expects($this->never())->method('releaseFromCoroutine');

        $coWrapper = new CoWrapper(new ServicePoolContainer([new ServicePoolEntry($pool)]));

        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->once())->method('run');

        $loggedMessage = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->willReturnCallback(static function (string $message) use (&$loggedMessage): void {
                $loggedMessage = $message;
            });

        $withScheduler = new WithScheduler($scheduler, $coWrapper, $logger, self::lockFactory(), self::heartbeat());

        // No run() wrapper - this is the whole point of the test.
        $withScheduler->tick();

        static::assertNotNull($loggedMessage);
        static::assertStringContainsString('no coroutine context', $loggedMessage);
    }

    public function testTickSkipsWhileAnotherProcessHoldsTheLock(): void
    {
        // Models the actual production failure: two separate WithScheduler instances (one
        // per OS process, e.g. Swoole's master and a manager-process tick spillover) sharing
        // only the lock backend, not any in-process state. Without the lock, both ticks'
        // scheduler->run() calls fired within microseconds of each other.
        $lockFactory = self::lockFactory();
        $second = $this->createMock(Scheduler::class);
        $second->expects($this->never())->method('run');
        $secondWithScheduler = new WithScheduler(
            $second,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
            $lockFactory,
            self::heartbeat(),
        );

        $first = $this->createMock(Scheduler::class);
        $first
            ->expects($this->once())
            ->method('run')
            ->willReturnCallback(static function () use ($secondWithScheduler): void {
                $secondWithScheduler->tick();
            });
        $firstWithScheduler = new WithScheduler(
            $first,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
            $lockFactory,
            self::heartbeat(),
        );

        run(static function () use ($firstWithScheduler): void {
            $firstWithScheduler->tick();
        });
    }

    public function testWatchdogForceReleasesTheLockWhenRunOverrunsTheTimeout(): void
    {
        // This is the only test here that actually suspends a coroutine (Coroutine::sleep)
        // and drives the Swoole event loop. Doing that while Xdebug's coverage driver is
        // recording reliably segfaults the process on shutdown under CI (exit 139, after
        // every test has already passed). Every synchronous path through tick() is covered
        // by the other tests; skip just this one when coverage is being collected.
        $xdebugModes = function_exists('xdebug_info') ? xdebug_info('mode') : null;
        if (is_array($xdebugModes) && in_array('coverage', $xdebugModes, true)) {
            static::markTestSkipped('Swoole coroutine suspend + Xdebug coverage segfaults on shutdown');
        }

        $scheduler = $this->createMock(Scheduler::class);
        $scheduler
            ->expects($this->once())
            ->method('run')
            ->willReturnCallback(static function (): void {
                // Outlasts the 1s timeout below; the Timer::after watchdog fires while this
                // is still yielded and force-releases the lock + $running flag.
                Coroutine::sleep(2);
            });

        $loggedError = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->method('error')
            ->willReturnCallback(static function (string $message) use (&$loggedError): void {
                $loggedError = $message;
            });

        $lockFactory = self::lockFactory();
        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            $logger,
            $lockFactory,
            self::heartbeat(),
            1,
        );

        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });

        static::assertStringContainsString('force-released the lock', (string) $loggedError);
        // The watchdog released the semaphore rather than leaving it held: a fresh acquire
        // of the same resource ('app-scheduler-tick') now succeeds.
        static::assertTrue($lockFactory->createLock('app-scheduler-tick')->acquire());
    }

    private static function lockFactory(): LockFactory
    {
        return new LockFactory(new InMemoryStore());
    }

    private static function heartbeat(): SchedulerHeartbeat
    {
        return new SchedulerHeartbeat(new Psr16Cache(new ArrayAdapter()), new MockClock(), new NullLogger());
    }

    private static function emptyCoWrapper(): CoWrapper
    {
        return new CoWrapper(new ServicePoolContainer([]));
    }

    /**
     * @return list<string>
     */
    private static function lockRegistryFiles(): array
    {
        $property = new ReflectionClass(LockRegistry::class)->getProperty('files');

        /** @var list<string> $files */
        return $property->getValue();
    }
}
