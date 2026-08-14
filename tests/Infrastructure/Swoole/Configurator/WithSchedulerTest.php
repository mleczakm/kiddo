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
use Symfony\Component\Cache\LockRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

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

        new WithScheduler($scheduler, $debugDataHolder)
            ->tick();
    }

    public function testTickSkipsWhileAlreadyRunning(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $debugDataHolder = $this->createMock(BacktraceDebugDataHolder::class);
        $debugDataHolder->expects($this->once())
            ->method('reset');

        $withScheduler = new WithScheduler($scheduler, $debugDataHolder);

        $scheduler->expects($this->once())
            ->method('run')
            ->willReturnCallback(function () use ($withScheduler): void {
                // Simulates an overlapping tick firing while this one is still in-flight.
                $withScheduler->tick();
            });

        $withScheduler->tick();
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

        $withScheduler = new WithScheduler($scheduler, $debugDataHolder);

        try {
            $withScheduler->tick();
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        // A stuck/failed tick must not permanently block future ticks from running.
        $withScheduler->tick();
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
