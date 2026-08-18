<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

readonly class MessengerConnectionResetter
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
    ) {}

    #[AsEventListener(event: WorkerMessageReceivedEvent::class)]
    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $_event): void
    {
        $this->connectionEnsurer->ensureConnection();
    }
}
