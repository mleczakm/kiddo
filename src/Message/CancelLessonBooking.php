<?php

declare(strict_types=1);

namespace App\Message;

final class CancelLessonBooking
{
    public function __construct(
        private int $bookingId,
        private int $lessonId,
        private int $cancelledById,
        private ?string $reason = null
    ) {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getLessonId(): int
    {
        return $this->lessonId;
    }

    public function getCancelledById(): int
    {
        return $this->cancelledById;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
