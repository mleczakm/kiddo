<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

/**
 * Temporary, dev-only: periodic tracer check independent of traffic, so leaks
 * in scheduler/timer code (not driven by HTTP requests) show up too.
 */
final class WithLeakTracer implements Configurator
{
    private int $tickId;

    public function __destruct()
    {
        Timer::clear($this->tickId);
    }

    public function configure(Server $server): void
    {
        if (!\function_exists('swoole_tracer_leak_detect')) {
            return;
        }

        $this->tickId = Timer::tick(30_000, static function (): void {
            swoole_tracer_leak_detect();
        });
    }
}
