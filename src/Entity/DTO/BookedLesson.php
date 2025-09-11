<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use App\Entity\Lesson;
use App\Entity\Booking;
use Symfony\Component\Uid\Ulid;

class BookedLesson
{
    public function __construct(
        public readonly Ulid $lessonId
    ) {}

    public function entity(Booking $booking): ?Lesson
    {
        return $booking->getLessons()
            ->get($this->lessonId->toString());
    }

    public function isBooked(): bool
    {
        // Assuming a lesson is booked by default when created
        return true;
    }
}
