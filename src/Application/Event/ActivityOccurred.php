<?php

declare(strict_types=1);

namespace App\Application\Event;

use App\Entity\ActivityType;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched whenever something worth showing in the admin activity feed
 * happens, from anywhere in the app — a message handler, a workflow
 * transition subscriber, or a LiveComponent action that mutates entities
 * directly without going through the command bus. A single subscriber
 * turns this into a persisted ActivityLog row.
 *
 * Carries the subject as a plain id rather than a User entity: this event
 * may be handled well after the caller's EntityManager context, and an id
 * is immune to the entity going stale/detached in the meantime.
 */
final class ActivityOccurred extends Event
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly ActivityType $type,
        public readonly string $title,
        public readonly ?int $subjectId = null,
        public readonly ?string $summary = null,
        public readonly ?string $url = null,
        public readonly array $context = [],
        public readonly ?string $dedupeKey = null,
    ) {}
}
