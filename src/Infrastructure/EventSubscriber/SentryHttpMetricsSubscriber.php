<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\Sentry\MetricsRecorderInterface;
use Sentry\Unit;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final readonly class SentryHttpMetricsSubscriber
{
    public function __construct(
        private MetricsRecorderInterface $metrics,
    ) {}

    #[AsEventListener(event: RequestEvent::class)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->metrics->count('requests.total', 1);
    }

    #[AsEventListener(event: ResponseEvent::class)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $statusCode = $event->getResponse()->getStatusCode();

        $this->metrics->count('responses.total', 1);
        $this->metrics->count('responses.' . intdiv($statusCode, 100) . 'xx', 1);

        $requestTime = $event->getRequest()->server->get('REQUEST_TIME_FLOAT');
        if (is_numeric($requestTime)) {
            $this->metrics->distribution(
                'requests.duration_ms',
                (microtime(true) - (float) $requestTime) * 1000,
                unit: Unit::millisecond(),
            );
        }
    }
}
