<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Infrastructure\Messenger\PreventAsyncDispatchInTaskWorkerMiddleware;
use App\Infrastructure\Messenger\TaskWorkerContextInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

#[Group('unit')]
final class PreventAsyncDispatchInTaskWorkerMiddlewareTest extends MiddlewareTestCase
{
    public function testDoesNotModifyOutboundMessageOutsideAsyncContext(): void
    {
        $middleware = $this->createMiddleware(inTaskWorker: false);
        $envelope = new Envelope(new \stdClass());

        $result = $middleware->handle($envelope, $this->getStackMock());

        self::assertNull($result->last(TransportNamesStamp::class));
    }

    public function testForcesSyncWhenRunningInTaskWorker(): void
    {
        $middleware = $this->createMiddleware(inTaskWorker: true);
        $envelope = new Envelope(new \stdClass());

        $result = $middleware->handle($envelope, $this->getStackMock());

        self::assertSame(['sync'], $result->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testForcesSyncForNestedDispatchWhileHandlingAsyncMessage(): void
    {
        $middleware = $this->createMiddleware(inTaskWorker: false);
        $capture = new \stdClass();
        $capture->nestedEnvelope = null;

        $terminal = new readonly class ($capture) implements MiddlewareInterface {
            public function __construct(
                private \stdClass $capture
            ) {}

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->capture->nestedEnvelope = $envelope;

                return $envelope;
            }
        };

        $stack = new StackMiddleware([
            $middleware,
            new readonly class ($middleware, $terminal) implements MiddlewareInterface {
                public function __construct(
                    private PreventAsyncDispatchInTaskWorkerMiddleware $middleware,
                    private MiddlewareInterface $terminal,
                ) {}

                public function handle(Envelope $envelope, StackInterface $stack): Envelope
                {
                    $envelope = $stack->next()
                        ->handle($envelope, $stack);

                    if ($envelope->last(ReceivedStamp::class) !== null) {
                        return $this->middleware->handle(
                            new Envelope(new \stdClass()),
                            new StackMiddleware([$this->middleware, $this->terminal]),
                        );
                    }

                    return $envelope;
                }
            },
            $terminal,
        ]);

        $middleware->handle(new Envelope(new \stdClass(), [new ReceivedStamp('async')]), $stack);

        self::assertNotNull($capture->nestedEnvelope);
        self::assertSame(['sync'], $capture->nestedEnvelope->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testDoesNotOverrideExistingTransportNamesStamp(): void
    {
        $middleware = $this->createMiddleware(inTaskWorker: true);
        $envelope = new Envelope(new \stdClass(), [new TransportNamesStamp('async')]);

        $result = $middleware->handle($envelope, $this->getStackMock());

        self::assertSame(['async'], $result->last(TransportNamesStamp::class)?->getTransportNames());
    }

    private function createMiddleware(bool $inTaskWorker): PreventAsyncDispatchInTaskWorkerMiddleware
    {
        $context = new readonly class ($inTaskWorker) implements TaskWorkerContextInterface {
            public function __construct(
                private bool $inTaskWorker
            ) {}

            public function isInTaskWorker(): bool
            {
                return $this->inTaskWorker;
            }
        };

        return new PreventAsyncDispatchInTaskWorkerMiddleware($context);
    }
}
