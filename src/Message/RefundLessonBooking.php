<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Ulid;

final class RefundLessonBooking
{
    public function __construct(
        private Ulid $bookingId,
        private Ulid $lessonId,
        private Ulid $refundedById,
        private ?string $reason = null
    ) {}

    public function getBookingId(): Ulid
    {
        return $this->bookingId;
    }

    public function getLessonId(): Ulid
    {
        return $this->lessonId;
    }

    public function getRefundedById(): Ulid
    {
        return $this->refundedById;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
