<?php

declare(strict_types=1);

namespace App\Infrastructure\Sentry;

use Sentry\Unit;

use function Sentry\trace_metrics;

/**
 * Thin wrapper around Sentry's Trace Metrics API (\Sentry\trace_metrics(), not
 * the deprecated \Sentry\metrics() no-op). Flushes immediately after every
 * write rather than relying solely on the HTTP-only kernel.terminate flush,
 * since workflow/activity metrics are also emitted from Messenger consumers,
 * the scheduler tick and CLI commands, none of which trigger that listener —
 * without an explicit flush those metrics would sit in a bounded buffer and
 * eventually get silently evicted.
 */
final readonly class MetricsRecorder implements MetricsRecorderInterface
{
    public function count(string $name, int|float $value, array $attributes = []): void
    {
        trace_metrics()->count($name, $value, $attributes);
        trace_metrics()
            ->flush();
    }

    public function distribution(string $name, int|float $value, array $attributes = [], ?Unit $unit = null): void
    {
        trace_metrics()->distribution($name, $value, $attributes, $unit);
        trace_metrics()
            ->flush();
    }
}
