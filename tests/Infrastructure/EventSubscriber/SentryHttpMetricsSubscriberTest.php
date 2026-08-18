<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Infrastructure\EventSubscriber\SentryHttpMetricsSubscriber;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Group('unit')]
class SentryHttpMetricsSubscriberTest extends TestCase
{
    private MetricsRecorderInterface&MockObject $metrics;

    private SentryHttpMetricsSubscriber $subscriber;

    private HttpKernelInterface&MockObject $kernel;

    #[\Override]
    protected function setUp(): void
    {
        $this->metrics = $this->createMock(MetricsRecorderInterface::class);
        $this->subscriber = new SentryHttpMetricsSubscriber($this->metrics);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testOnKernelRequestTracksTotalForMainRequest(): void
    {
        $this->metrics->expects($this->once())->method('count')->with('requests.total', 1);

        $this->subscriber->onKernelRequest(
            new RequestEvent($this->kernel, new Request(), HttpKernelInterface::MAIN_REQUEST),
        );
    }

    public function testOnKernelRequestIgnoresSubRequest(): void
    {
        $this->metrics->expects($this->never())->method('count');

        $this->subscriber->onKernelRequest(
            new RequestEvent($this->kernel, new Request(), HttpKernelInterface::SUB_REQUEST),
        );
    }

    public function testOnKernelResponseTracksCountersAndDurationForMainRequest(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_TIME_FLOAT', microtime(true) - 0.05);

        $calls = [];
        $this->metrics
            ->expects($this->exactly(2))
            ->method('count')
            ->willReturnCallback(static function (string $name, int|float $value) use (&$calls): void {
                $calls[] = [$name, $value];
            });
        $this->metrics
            ->expects($this->once())
            ->method('distribution')
            ->with('requests.duration_ms', static::greaterThan(0));

        $this->subscriber->onKernelResponse(
            new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response('', 404)),
        );

        static::assertSame(
            [
                ['responses.total', 1],
                ['responses.4xx',   1],
            ],
            $calls,
        );
    }

    public function testOnKernelResponseIgnoresSubRequest(): void
    {
        $this->metrics->expects($this->never())->method('count');
        $this->metrics->expects($this->never())->method('distribution');

        $this->subscriber->onKernelResponse(
            new ResponseEvent($this->kernel, new Request(), HttpKernelInterface::SUB_REQUEST, new Response()),
        );
    }
}
