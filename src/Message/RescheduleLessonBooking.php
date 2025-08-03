<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Ulid;

final class RescheduleLessonBooking
{
    public function __construct(
        private Ulid $bookingId,
        private Ulid $oldLessonId,
        private Ulid $newLessonId,
        private Ulid $rescheduledById,
        private ?string $reason = null
    ) {}

    public function getBookingId(): Ulid
    {
        return $this->bookingId;
    }

    public function getOldLessonId(): Ulid
    {
        return $this->oldLessonId;
    }

    public function getNewLessonId(): Ulid
    {
        return $this->newLessonId;
    }

    public function getRescheduledById(): Ulid
    {
        return $this->rescheduledById;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
