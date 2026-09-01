<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SampleResourceUsage;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use App\Infrastructure\System\ProcResourceUsageProbe;
use Psr\Log\LoggerInterface;
use Sentry\Unit;

/**
 * Auto-registered as a message handler by the `App\Application\CommandHandler\` block in
 * config/services.yaml (tags: messenger.message_handler), same as its siblings.
 *
 * The snapshot is PID-namespace-wide, so it does not matter which process the async
 * transport happens to run this in. Sentry gives the historical graph; the log line lands
 * on stdout as JSON (prod monolog `main` handler) so the same data is greppable straight
 * from `docker logs kiddo`.
 */
final readonly class SampleResourceUsageHandler
{
    public function __construct(
        private ProcResourceUsageProbe $probe,
        private MetricsRecorderInterface $metrics,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SampleResourceUsage $_command): void
    {
        $snapshot = $this->probe->capture();

        $this->metrics->distribution('runtime.memory.rss_total', $snapshot->totalRssBytes(), [], Unit::byte());
        $this->metrics->distribution(
            'runtime.memory.rss_max_process',
            $snapshot->maxProcessRssBytes(),
            [],
            Unit::byte(),
        );
        $this->metrics->distribution('runtime.process.count', $snapshot->processCount);
        $this->metrics->distribution('runtime.fd.open_total', $snapshot->totalOpenFds);
        $this->metrics->distribution('runtime.tcp.in_use', $snapshot->tcpInUse);
        $this->metrics->distribution('runtime.tcp.allocated', $snapshot->tcpAllocated);

        $this->logger->info('Resource usage sampled', $snapshot->toArray());
    }
}
