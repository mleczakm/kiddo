<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Swoole\Configurator\WithScheduler;
use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\TestCase;
use Swoole\Timer;
use Symfony\Component\Messenger\MessageBusInterface;

final class WithSchedulerTest extends TestCase
{
    public function testRegisterSwooleTick(): void
    {
        self::assertEmpty(iterator_to_array(Timer::list()));
        ($withScheduler = new WithScheduler(new Scheduler($this->createMock(
            MessageBusInterface::class
        ), [])))->configure($this->createMock(\Swoole\Http\Server::class));

        $ticks = iterator_to_array(Timer::list());

        self::assertNotEmpty($ticks);

        $withScheduler->__destruct();

        self::assertEmpty(iterator_to_array(Timer::list()));
    }
}
