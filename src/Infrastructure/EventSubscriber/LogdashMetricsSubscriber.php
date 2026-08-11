<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use Logdash\Logdash;
use Logdash\Metrics\BaseMetrics;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class LogdashMetricsSubscriber
{
    private float $requestStartTime;

    public function __construct(
        private Logdash $logdash,
    ) {
        $this->requestStartTime = microtime(true);
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 255)]
    public function onRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->metrics()
            ->mutate('requests.total', 1);
        $this->metrics()
            ->mutate('requests.active', 1);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -255)]
    public function onResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();

        $this->metrics()
            ->mutate('responses.total', 1);
        $this->metrics()
            ->mutate('responses.active', -1);

        // Track response status codes
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->metrics()
                ->mutate('responses.2xx', 1);
        } elseif ($statusCode >= 300 && $statusCode < 400) {
            $this->metrics()
                ->mutate('responses.3xx', 1);
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            $this->metrics()
                ->mutate('responses.4xx', 1);
        } elseif ($statusCode >= 500) {
            $this->metrics()
                ->mutate('responses.5xx', 1);
        }
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onTerminate(TerminateEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $requestDuration = (microtime(true) - $this->requestStartTime) * 1000; // Convert to milliseconds
        $this->metrics()
            ->set('requests.last_duration_ms', $requestDuration);
        $this->metrics()
            ->mutate('requests.active', -1);
    }

    private function metrics(): BaseMetrics
    {
        return $this->logdash->metrics();
    }
}
