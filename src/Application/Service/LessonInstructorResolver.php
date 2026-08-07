<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Lesson;
use App\Entity\User;

/**
 * Flattens the instructors across one or more lessons (direct + series-level,
 * see Lesson::getAllInstructors()) into a deduped list, optionally excluding
 * one user — e.g. the person who just performed the action, so they don't
 * get notified about their own booking/reschedule/cancellation/edit.
 */
final readonly class LessonInstructorResolver
{
    /**
     * @param iterable<Lesson> $lessons
     * @return list<User>
     */
    public function resolve(iterable $lessons, ?User $exclude = null): array
    {
        $result = [];
        $seenIds = [];

        foreach ($lessons as $lesson) {
            foreach ($lesson->getAllInstructors() as $instructor) {
                $id = $instructor->getId();
                if ($id === null || in_array($id, $seenIds, true)) {
                    continue;
                }
                if ($exclude !== null && $id === $exclude->getId()) {
                    continue;
                }
                $seenIds[] = $id;
                $result[] = $instructor;
            }
        }

        return $result;
    }
}
