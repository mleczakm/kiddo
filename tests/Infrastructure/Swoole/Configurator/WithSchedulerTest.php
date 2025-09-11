<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole\Configurator;

use PHPUnit\Framework\Attributes\Group;
use App\Infrastructure\Swoole\Configurator\WithScheduler;
use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Server;
use Swoole\Timer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Group('unit')]
final class WithSchedulerTest extends TestCase
{
    public function testRegisterSwooleTick(): void
    {
        self::assertEmpty(iterator_to_array(Timer::list()));
        ($withScheduler = new WithScheduler(
            new Scheduler($this->createMock(MessageBusInterface::class), []),
            $this->createMock(ResetInterface::class)
        ))->configure($this->createMock(Server::class));

        $ticks = iterator_to_array(Timer::list());

        self::assertNotEmpty($ticks);

        $withScheduler->__destruct();

        self::assertEmpty(iterator_to_array(Timer::list()));
    }
}
