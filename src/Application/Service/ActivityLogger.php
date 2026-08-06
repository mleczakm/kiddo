<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Event\ActivityOccurred;
use App\Entity\ActivityType;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Single, uniform entry point for recording something into the admin
 * "Ostatnia aktywność" activity feed — usable from message handlers,
 * workflow subscribers, or LiveComponents/services that mutate entities
 * directly without going through the command bus. Internally dispatches a
 * Symfony event so the actual persistence stays in one place
 * (ActivityLogSubscriber), regardless of caller.
 */
final readonly class ActivityLogger
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function log(
        ActivityType $type,
        string $title,
        ?User $subject = null,
        ?string $summary = null,
        ?string $url = null,
        array $context = [],
        ?string $dedupeKey = null,
    ): void {
        $this->eventDispatcher->dispatch(new ActivityOccurred(
            type: $type,
            title: $title,
            subject: $subject,
            summary: $summary,
            url: $url,
            context: $context,
            dedupeKey: $dedupeKey,
        ));
    }
}
