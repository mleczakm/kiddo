<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;

/**
 * Temporary, dev-only: reports growing allocation sites after each request via
 * ext-swoole's built-in tracer. No-ops unless swoole.leak_detection=On
 * (.docker/php/ini/swoole-tracer.ini, mounted only for the dev-local compose target).
 */
final readonly class LeakTracerRequestHandler implements RequestHandler
{
    public function __construct(
        private RequestHandler $innerHandler,
    ) {}

    public function handle(Request $request, Response $response): void
    {
        $this->innerHandler->handle($request, $response);

        if (\function_exists('swoole_tracer_leak_detect')) {
            swoole_tracer_leak_detect();
        }
    }
}
