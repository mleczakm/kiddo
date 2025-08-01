<?php

declare(strict_types=1);

namespace App\Message;

final class RescheduleLessonBooking
{
    public function __construct(
        private int $bookingId,
        private int $oldLessonId,
        private int $newLessonId,
        private int $rescheduledById,
        private ?string $reason = null
    ) {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getOldLessonId(): int
    {
        return $this->oldLessonId;
    }

    public function getNewLessonId(): int
    {
        return $this->newLessonId;
    }

    public function getRescheduledById(): int
    {
        return $this->rescheduledById;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
