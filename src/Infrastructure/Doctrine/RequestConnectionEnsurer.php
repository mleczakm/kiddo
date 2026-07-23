<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class RequestConnectionEnsurer
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
    ) {}

    #[AsEventListener(event: RequestEvent::class, priority: 10)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $this->connectionEnsurer->ensureConnection();
    }
}
