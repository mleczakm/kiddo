<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Scheduler\Event\PreRunEvent;

final readonly class SchedulerConnectionResetter
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
    ) {}

    #[AsEventListener(event: PreRunEvent::class)]
    public function onPreRun(PreRunEvent $event): void
    {
        $this->connectionEnsurer->ensureConnection();
    }
}
