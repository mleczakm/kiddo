<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Swoole\Configurator\WithScheduler;
use App\Infrastructure\Symfony\Scheduler;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
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

        $withScheduler = new WithScheduler($scheduler, $debugDataHolder, self::emptyCoWrapper());

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

        $withScheduler = new WithScheduler($scheduler, $debugDataHolder, self::emptyCoWrapper());

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
        $debugDataHolder->expects($this->once())
            ->method('reset');

        $withScheduler = new WithScheduler($scheduler, $debugDataHolder, self::emptyCoWrapper());

        $caught = null;
        run(static function () use ($withScheduler, &$caught): void {
            try {
                $withScheduler->tick();
            } catch (RuntimeException $e) {
                $caught = $e;
            }
        });

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertSame('boom', $caught->getMessage());

        // A stuck/failed tick must not permanently block future ticks from running.
        run(static function () use ($withScheduler): void {
            $withScheduler->tick();
        });
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
