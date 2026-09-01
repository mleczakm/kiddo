<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\SampleResourceUsage;
use App\Application\CommandHandler\SampleResourceUsageHandler;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use App\Infrastructure\System\ProcResourceUsageProbe;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Unit;

#[Group('unit')]
final class SampleResourceUsageHandlerTest extends TestCase
{
    public function testEmitsMemorySocketAndProcessMetricsPlusOneLogLine(): void
    {
        $recorded = [];
        $metrics = $this->createMock(MetricsRecorderInterface::class);
        $metrics
            ->method('distribution')
            ->willReturnCallback(static function (
                string $name,
                int|float $value,
                array $_attributes = [],
                ?Unit $unit = null,
            ) use (&$recorded): void {
                $recorded[$name] = ['value' => $value, 'unit' => $unit?->__toString()];
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('info')
            ->with(
                'Resource usage sampled',
                static::callback(
                    static fn(array $context): bool => (
                        array_key_exists('total_rss_mib', $context) && array_key_exists('tcp_allocated', $context)
                    ),
                ),
            );

        (new SampleResourceUsageHandler(new ProcResourceUsageProbe(), $metrics, $logger))(new SampleResourceUsage());

        static::assertArrayHasKey('runtime.memory.rss_total', $recorded);
        static::assertArrayHasKey('runtime.memory.rss_max_process', $recorded);
        static::assertArrayHasKey('runtime.process.count', $recorded);
        static::assertArrayHasKey('runtime.fd.open_total', $recorded);
        static::assertArrayHasKey('runtime.tcp.in_use', $recorded);
        static::assertArrayHasKey('runtime.tcp.allocated', $recorded);
        static::assertSame(Unit::byte()->__toString(), $recorded['runtime.memory.rss_total']['unit']);
        static::assertGreaterThan(0, $recorded['runtime.memory.rss_total']['value']);
        static::assertNull($recorded['runtime.process.count']['unit']);
    }
}
