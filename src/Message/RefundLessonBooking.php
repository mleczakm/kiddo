<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\User;
use Symfony\Component\Uid\Ulid;

final readonly class RefundLessonBooking
{
    public function __construct(
        private Ulid $bookingId,
        private Ulid $lessonId,
        private User $refundedBy,
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

    public function getRefundedBy(): User
    {
        return $this->refundedBy;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
