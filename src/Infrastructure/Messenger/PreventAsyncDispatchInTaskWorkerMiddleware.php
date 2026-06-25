<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Swoole task workers cannot call Server->task() again. When a handler running inside
 * an async message (or a Swoole task worker) dispatches another message routed to async,
 * force it onto the sync transport instead.
 */
final class PreventAsyncDispatchInTaskWorkerMiddleware implements MiddlewareInterface
{
    private int $asyncHandlerDepth = 0;

    public function __construct(
        private readonly TaskWorkerContextInterface $taskWorkerContext,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (
            ! $envelope->last(ReceivedStamp::class)
            && ! $envelope->all(TransportNamesStamp::class)
            && ($this->asyncHandlerDepth > 0 || $this->taskWorkerContext->isInTaskWorker())
        ) {
            $envelope = $envelope->with(new TransportNamesStamp('sync'));
        }

        $receivedStamp = $envelope->last(ReceivedStamp::class);
        $isHandlingAsyncMessage = $receivedStamp !== null && $receivedStamp->getTransportName() === 'async';

        if ($isHandlingAsyncMessage) {
            ++$this->asyncHandlerDepth;
        }

        try {
            return $stack->next()
                ->handle($envelope, $stack);
        } finally {
            if ($isHandlingAsyncMessage) {
                --$this->asyncHandlerDepth;
            }
        }
    }
}
