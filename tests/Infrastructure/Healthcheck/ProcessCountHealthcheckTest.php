<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Healthcheck;

use App\Infrastructure\Healthcheck\ProcessCountHealthcheck;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ProcessCountHealthcheckTest extends TestCase
{
    public function testPassesWhenProcessCountIsWithinThreshold(): void
    {
        // The real process count in this PID namespace is unpredictable across environments,
        // but always >= 1 (this PHP process itself), so a threshold this high is always
        // "within" regardless of where the test runs.
        $response = new ProcessCountHealthcheck(PHP_INT_MAX)->check();

        self::assertTrue($response->getResult());
        self::assertSame('process_count', $response->getName());
        self::assertGreaterThanOrEqual(1, $response->getParams()['count']);
    }

    public function testFailsWhenProcessCountExceedsThreshold(): void
    {
        // Symmetric to the above: a threshold of 0 is always exceeded, since at least this
        // PHP process is running.
        $response = new ProcessCountHealthcheck(0)->check();

        self::assertFalse($response->getResult());
        self::assertStringContainsString('exceeds threshold 0', $response->getMessage());
    }
}
