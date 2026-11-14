<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class RecordingMiddleware implements MiddlewareInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ?\Throwable $failure = null,
    ) {}

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        ++$this->calls;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $envelope;
    }
}
