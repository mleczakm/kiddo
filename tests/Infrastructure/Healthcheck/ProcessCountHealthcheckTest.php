<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Healthcheck;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SwooleBundle\Observability\HealthCheck\ProcessCountHealthCheck;
use SwooleBundle\Observability\System\ProcResourceUsageProbe;

#[Group('unit')]
final class ProcessCountHealthcheckTest extends TestCase
{
    public function testPassesWhenProcessCountIsWithinThreshold(): void
    {
        // The real process count in this PID namespace is unpredictable across environments,
        // but always >= 1 (this PHP process itself), so a threshold this high is always
        // "within" regardless of where the test runs.
        $response = new ProcessCountHealthCheck(new ProcResourceUsageProbe(), PHP_INT_MAX)->check();

        static::assertTrue($response->getResult());
        static::assertSame('process_count', $response->getName());
        static::assertGreaterThanOrEqual(1, $response->getParams()['count']);
    }

    public function testFailsWhenProcessCountExceedsThreshold(): void
    {
        // Symmetric to the above: a threshold of 0 is always exceeded, since at least this
        // PHP process is running.
        $response = new ProcessCountHealthCheck(new ProcResourceUsageProbe(), 0)->check();

        static::assertFalse($response->getResult());
        static::assertStringContainsString('exceeds threshold 0', $response->getMessage());
    }
}
