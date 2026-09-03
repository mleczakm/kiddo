<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

/**
 * Invokable adapter so {@see ConnectionEnsurerInterface} can be wired as the
 * `swoole_bundle_scheduler.pre_run` callable - run before each scheduler poll pass and
 * before each message dispatch to make sure the pooled ORM + `cache` DBAL connections are
 * alive before a stateful schedule's `Checkpoint::save()` touches them.
 */
final readonly class SchedulerConnectionGuard
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
    ) {}

    public function __invoke(): void
    {
        $this->connectionEnsurer->ensureConnection();
    }
}
