<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Issues the current month's charge for every active monthly subscription that
 * has not been billed for it yet. Scheduled monthly; safe to re-run (each
 * subscription tracks its last charged period).
 */
final readonly class IssueSubscriptionCharges
{
    public function __construct(
        public \DateTimeImmutable $period = new \DateTimeImmutable(),
    ) {}
}
