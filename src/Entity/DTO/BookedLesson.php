<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use App\Entity\Booking;
use Symfony\Component\Uid\Ulid;

class BookedLesson
{
    public function __construct(
        public readonly Ulid $lessonId
    ) {}

    public function entity(Booking $booking)
    {
        return $booking->getLessons()
            ->get($this->lessonId->toString());
    }
}
