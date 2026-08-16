<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Swoole\Configurator\WithScheduler;
use App\Infrastructure\Symfony\Scheduler;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use Symfony\Component\Cache\LockRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

use function Swoole\Coroutine\run;

#[Group('unit')]
final class WithSchedulerTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $originalLockRegistryFiles;

    protected function setUp(): void
    {
        $this->originalLockRegistryFiles = self::lockRegistryFiles();
    }

    protected function tearDown(): void
    {
        LockRegistry::setFiles($this->originalLockRegistryFiles);
    }

    public function testRegisterSwooleTick(): void
    {
        self::assertEmpty(iterator_to_array(Timer::list()));
        ($withScheduler = new WithScheduler(
            new Scheduler($this->createMock(MessageBusInterface::class), []),
            $this->createMock(BacktraceDebugDataHolder::class),
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
        ))->configure($this->createMock(Server::class));

        $ticks = iterator_to_array(Timer::list());

        self::assertNotEmpty($ticks);

        $withScheduler->__destruct();

        self::assertEmpty(iterator_to_array(Timer::list()));
    }

    public function testConfigureDisablesLockRegistryFileLocking(): void
    {
        LockRegistry::setFiles(['some/file.php']);

        ($withScheduler = new WithScheduler(
            new Scheduler($this->createMock(MessageBusInterface::class), []),
            $this->createMock(BacktraceDebugDataHolder::class),
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
        ))->configure($this->createMock(Server::class));

        self::assertSame([], self::lockRegistryFiles());

        $withScheduler->__destruct();
    }

    public function testTickRunsSchedulerAndResetsDebugDataHolder(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->once())
            ->method('run');

        $debugDataHolder = $this->createMock(BacktraceDebugDataHolder::class);
        $debugDataHolder->expects($this->once())
            ->method('reset');

        $withScheduler = new WithScheduler(
            $scheduler,
            $debugDataHolder,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
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
        $pool->expects($this->once())
            ->method('releaseFromCoroutine');

        $coWrapper = new CoWrapper(new ServicePoolContainer([new ServicePoolEntry($pool)]));

        $withScheduler = new WithScheduler(
            $this->createMock(Scheduler::class),
            $this->createMock(BacktraceDebugDataHolder::class),
            $coWrapper,
            $this->createMock(LoggerInterface::class),
        );

        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });
    }

    public function testTickSkipsWhileAlreadyRunning(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $debugDataHolder = $this->createMock(BacktraceDebugDataHolder::class);
        $debugDataHolder->expects($this->once())
            ->method('reset');

        $withScheduler = new WithScheduler(
            $scheduler,
            $debugDataHolder,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
        );

        $scheduler->expects($this->once())
            ->method('run')
            ->willReturnCallback(function () use ($withScheduler): void {
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
        $scheduler->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(function (): void {
                static $calls = 0;
                ++$calls;

                if ($calls === 1) {
                    throw new RuntimeException('boom');
                }
            });

        $debugDataHolder = $this->createMock(BacktraceDebugDataHolder::class);
        $debugDataHolder->expects($this->exactly(2))
            ->method('reset');

        $withScheduler = new WithScheduler(
            $scheduler,
            $debugDataHolder,
            self::emptyCoWrapper(),
            $this->createMock(LoggerInterface::class),
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
        $scheduler->expects($this->once())
            ->method('run')
            ->willThrowException($exception);

        // Asserting via ->with() on a mock invoked from inside Swoole\Coroutine\run() crashes
        // PHPUnit 11's parameter-matcher (it walks the call stack for the enclosing TestCase,
        // which a coroutine's separate stack doesn't have) - capture the call instead and
        // assert on it from the outer, non-coroutine stack once run() returns.
        $loggedMessage = null;
        $loggedContext = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->willReturnCallback(function (string $message, array $context) use (
                &$loggedMessage,
                &$loggedContext
            ): void {
                $loggedMessage = $message;
                $loggedContext = $context;
            });

        $withScheduler = new WithScheduler(
            $scheduler,
            $this->createMock(BacktraceDebugDataHolder::class),
            self::emptyCoWrapper(),
            $logger,
        );

        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });

        self::assertSame('Scheduler tick failed', $loggedMessage);
        self::assertSame([
            'exception' => $exception,
        ], $loggedContext);
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
        $pool->expects($this->never())
            ->method('releaseFromCoroutine');

        $coWrapper = new CoWrapper(new ServicePoolContainer([new ServicePoolEntry($pool)]));

        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->once())
            ->method('run');

        $debugDataHolder = $this->createMock(BacktraceDebugDataHolder::class);
        $debugDataHolder->expects($this->once())
            ->method('reset');

        $loggedMessage = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->willReturnCallback(function (string $message) use (&$loggedMessage): void {
                $loggedMessage = $message;
            });

        $withScheduler = new WithScheduler($scheduler, $debugDataHolder, $coWrapper, $logger);

        // No run() wrapper - this is the whole point of the test.
        $withScheduler->tick();

        self::assertNotNull($loggedMessage);
        self::assertStringContainsString('no coroutine context', $loggedMessage);
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
        $files = $property->getValue();

        return $files;
    }
}
