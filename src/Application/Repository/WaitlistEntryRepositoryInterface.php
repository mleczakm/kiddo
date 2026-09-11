<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Lesson;
use App\Entity\User;
use App\Entity\WaitlistEntry;

/**
 * @extends RepositoryInterface<WaitlistEntry>
 */
interface WaitlistEntryRepositoryInterface extends RepositoryInterface
{
    /** The caller's own active (waiting/offered) entry for a lesson, if any. */
    public function findActiveForUserAndLesson(User $user, Lesson $lesson): ?WaitlistEntry;

    /**
     * Active entries for a lesson, oldest first (queue order).
     *
     * @return list<WaitlistEntry>
     */
    public function findActiveForLesson(Lesson $lesson): array;

    /** How many entries currently hold an `offered` seat for a lesson. */
    public function countActiveOffers(Lesson $lesson): int;

    /** How many entries are currently active (waiting or offered) for a lesson. */
    public function countActiveForLesson(Lesson $lesson): int;

    /**
     * `offered` entries whose hold has elapsed at $now.
     *
     * @return list<WaitlistEntry>
     */
    public function findExpiredOffers(\DateTimeImmutable $now): array;

    /**
     * The caller's active entries across all lessons, queue order.
     *
     * @return list<WaitlistEntry>
     */
    public function findActiveForUser(User $user): array;
}
