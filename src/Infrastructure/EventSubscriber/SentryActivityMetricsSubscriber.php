<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Event\ActivityOccurred;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class SentryActivityMetricsSubscriber
{
    public function __construct(
        private MetricsRecorderInterface $metrics,
    ) {}

    #[AsEventListener(event: ActivityOccurred::class)]
    public function onActivityOccurred(ActivityOccurred $event): void
    {
        $this->metrics->count('activities.total', 1);
        $this->metrics->count('activities.' . $event->type->value, 1);
    }
}
