<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Lesson;
use App\Entity\User;

interface LessonSearchInterface
{
    /** @return list<Lesson> */
    public function findCancelledInRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    /** @return list<Lesson> */
    public function findByMetadataTitlePrefix(string $prefix): array;

    /**
     * Staff lesson search for the chat assistant: active lessons with a
     * fuzzy title match (ILIKE %query%), an optional [$from, $to] schedule
     * window, optionally scoped to lessons $instructor is assigned to
     * (directly or via the series — mirrors Lesson::getAllInstructors()).
     * Ordered by schedule ASC. Ignores public visibility so staff also see
     * hidden lessons.
     *
     * @return list<Lesson>
     */
    public function findForStaff(
        ?string $titleQuery,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?User $instructor,
        int $limit,
    ): array;
}
