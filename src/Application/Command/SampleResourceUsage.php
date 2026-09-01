<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Periodic trigger (see App\Infrastructure\Symfony\Scheduler\MainSchedule) that samples this
 * container's /proc resource usage and ships it to Sentry metrics plus the application log,
 * so memory growth between restarts can be watched as a time series instead of only being
 * glimpsed in the last handful of /health probes.
 */
final class SampleResourceUsage {}
