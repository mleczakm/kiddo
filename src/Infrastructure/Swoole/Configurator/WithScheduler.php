<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Symfony\Scheduler;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

final class WithScheduler implements Configurator
{
    private int $tickId;

    public function __construct(
        private readonly Scheduler $scheduler
    ) {
    }

    public function configure(Server $server): void
    {
        $this->tickId = Timer::tick(1000, function (): void {
            $this->scheduler->run();
        });

        $server->on('shutdown', function (): void {
            Timer::clear($this->tickId);
        });
    }

    public function __destruct()
    {
        Timer::clear($this->tickId);
    }
}
