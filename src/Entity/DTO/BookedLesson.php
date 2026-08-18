<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use App\Entity\Booking;
use App\Entity\Lesson;
use Symfony\Component\Uid\Ulid;

class BookedLesson
{
    public function __construct(
        public readonly Ulid $lessonId,
    ) {}

    /**
     * Booking::$lessons is a plain ManyToMany collection with no indexBy, so
     * it's keyed by position (0, 1, 2…), never by lesson ID - Collection::get()
     * with a ULID string here always missed and returned null. That made every
     * entity()-dependent date check (areAllLessonsInPast, getModifiableLessons,
     * getFutureActiveLessons) vacuously pass/fail instead of reflecting the
     * actual schedule. Scan by ID instead.
     */
    public function entity(Booking $booking): ?Lesson
    {
        foreach ($booking->getLessons() as $lesson) {
            if ($lesson->getId()->equals($this->lessonId)) {
                return $lesson;
            }
        }

        return null;
    }

    public function isBooked(): bool
    {
        // Assuming a lesson is booked by default when created
        return true;
    }
}
