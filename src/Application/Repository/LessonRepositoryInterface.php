<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Lesson;
use App\Entity\Series;
use App\Entity\User;
use DateTimeImmutable;

/**
 * @extends RepositoryInterface<Lesson>
 */
interface LessonRepositoryInterface extends RepositoryInterface
{
    /** @return array<int, Lesson> */
    public function findAvailableLessonsForReschedule(
        Series $series,
        \DateTimeInterface $afterDate,
        int $maxResults = 10,
    ): array;

    /** @return Lesson[] */
    public function findActiveByDate(DateTimeImmutable $date): array;

    /** @return Lesson[] */
    public function findByFilters(
        ?string $query,
        ?int $age,
        string $week,
        ?int $limit = null,
        bool $orderByPopularity = false,
    ): array;

    /** @return array<int, Lesson> */
    public function findUpcoming(\DateTimeImmutable $since, int $limit): array;

    /** @return array<int, Lesson> */
    public function findUpcomingWithBookings(\DateTimeImmutable $since, int $limit): array;

    /** @return array<int, Lesson> */
    public function findUpcomingWithBookingsInRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled = false,
    ): array;

    /** @return array<int, Lesson> */
    public function findUpcomingInRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled = false,
    ): array;

    /** @return array<int, Lesson> */
    public function findUpcomingInRangeForInstructor(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled,
        User $instructor,
    ): array;

    /** @return list<Lesson> */
    public function findCancelledInRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    /** @return list<Lesson> */
    public function findByMetadataTitlePrefix(string $prefix): array;
}
