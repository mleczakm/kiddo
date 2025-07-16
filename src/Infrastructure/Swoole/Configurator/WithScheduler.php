<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Symfony\Scheduler;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use Symfony\Contracts\Service\ResetInterface;

final class WithScheduler implements Configurator
{
    private int $tickId;

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly ResetInterface $reset,
    ) {}

    public function __destruct()
    {
        Timer::clear($this->tickId);
    }

    public function configure(Server $server): void
    {
        $this->tickId = Timer::tick(1000, function (): void {
            $this->scheduler->run();
            $this->reset->reset();
        });

        $server->on('shutdown', function (): void {
            Timer::clear($this->tickId);
        });
    }
}
