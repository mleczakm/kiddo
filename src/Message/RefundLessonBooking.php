<?php

declare(strict_types=1);

namespace App\Message;

final class RefundLessonBooking
{
    public function __construct(
        private int $bookingId,
        private int $lessonId,
        private int $refundedById,
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

    public function getRefundedById(): int
    {
        return $this->refundedById;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
