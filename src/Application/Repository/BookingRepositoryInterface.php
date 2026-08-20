<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;

/**
 * @extends RepositoryInterface<Booking>
 */
interface BookingRepositoryInterface extends RepositoryInterface
{
    /** @return array<Booking> */
    public function findExpiredPendingBookings(\DateTimeImmutable $expirationTime): array;

    /** @return array<Booking> */
    public function findActiveBookings(): array;

    /** @return list<Booking> */
    public function findVisibleForUser(User $user): array;

    /**
     * @param array<Lesson> $lessons
     *
     * @return array<Booking>
     */
    public function findForUserAndLessons(User $user, array $lessons): array;

    /** @return array<Booking> */
    public function findForUserAndLesson(User $user, Lesson $lesson): array;

    /** @return array<Booking> */
    public function findByLesson(Lesson $lesson): array;

    public function countCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int;

    /** @return array<Booking> */
    public function findCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    /**
     * Bookings newest-first with the user eager-loaded, optionally filtered by
     * status and/or a case-insensitive match against the booking user's name/email.
     *
     * @return list<Booking>
     */
    public function findFiltered(?string $status, ?string $query, int $limit): array;
}
