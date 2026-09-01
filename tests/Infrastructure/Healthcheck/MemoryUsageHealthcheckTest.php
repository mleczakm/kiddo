<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Healthcheck;

use App\Infrastructure\Healthcheck\MemoryUsageHealthcheck;
use App\Infrastructure\System\ProcResourceUsageProbe;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class MemoryUsageHealthcheckTest extends TestCase
{
    public function testPassesWhenTotalResidentMemoryIsWithinThreshold(): void
    {
        // Real total RSS of this test's PID namespace is unpredictable but always well
        // under a terabyte, so this threshold is always "within" wherever the test runs.
        $response = new MemoryUsageHealthcheck(new ProcResourceUsageProbe(), 1_048_576)->check();

        static::assertTrue($response->getResult());
        static::assertSame('memory_usage', $response->getName());
        static::assertArrayHasKey('total_rss_mib', $response->getParams());
        static::assertArrayHasKey('tcp_allocated', $response->getParams());
        static::assertSame(1_048_576, $response->getParams()['threshold_mib']);
    }

    public function testFailsWhenTotalResidentMemoryExceedsThreshold(): void
    {
        // Symmetric to the above: this PHP process alone holds more than 1 MiB resident,
        // so a 1 MiB threshold is always exceeded.
        $response = new MemoryUsageHealthcheck(new ProcResourceUsageProbe(), 1)->check();

        static::assertFalse($response->getResult());
        static::assertStringContainsString('exceeds threshold 1 MiB', $response->getMessage());
    }

    public function testThresholdOfZeroDisablesTheFailureAndOnlyReports(): void
    {
        $response = new MemoryUsageHealthcheck(new ProcResourceUsageProbe(), 0)->check();

        static::assertTrue($response->getResult());
        static::assertSame(0, $response->getParams()['threshold_mib']);
    }
}
