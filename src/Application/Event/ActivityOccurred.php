<?php

declare(strict_types=1);

namespace App\Application\Event;

use App\Entity\ActivityType;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched whenever something worth showing in the admin activity feed
 * happens, from anywhere in the app — a message handler, a workflow
 * transition subscriber, or a LiveComponent action that mutates entities
 * directly without going through the command bus. A single subscriber
 * turns this into a persisted ActivityLog row.
 */
final class ActivityOccurred extends Event
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly ActivityType $type,
        public readonly string $title,
        public readonly ?User $subject = null,
        public readonly ?string $summary = null,
        public readonly ?string $url = null,
        public readonly array $context = [],
        public readonly ?string $dedupeKey = null,
    ) {}
}
