<?php

declare(strict_types=1);

namespace App\Infrastructure\Sentry;

use Sentry\Unit;

interface MetricsRecorderInterface
{
    /**
     * @param array<string, int|float|string|bool> $attributes
     */
    public function count(string $name, int|float $value, array $attributes = []): void;

    /**
     * @param array<string, int|float|string|bool> $attributes
     */
    public function distribution(string $name, int|float $value, array $attributes = [], ?Unit $unit = null): void;
}
