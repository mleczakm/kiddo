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
        private readonly Scheduler $scheduler,
    ) {}

    public function __destruct()
    {
        Timer::clear($this->tickId);
    }

    public function configure(Server $server): void
    {
        $this->tickId = Timer::tick(1000, function (): void {
            $this->scheduler->run();
        });





        $server->on('shutdown', function (): void {
            // memprof isn't actually loading in this image (php -m shows no memprof module),
            // so calling it unconditionally fatals the shutdown handler with "undefined function".
            if (\function_exists('memprof_dump_pprof')) {
                memprof_dump_pprof(fopen("profile-{$this->tickId}.heap", "w"));
            }
            Timer::clear($this->tickId);
        });
    }
}
