<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\User;
use Symfony\Component\Uid\Ulid;

final class CancelLessonBooking
{
    public function __construct(
        private Ulid $bookingId,
        private Ulid $lessonId,
        private User $cancelledBy,
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

    public function getCancelledBy(): User
    {
        return $this->cancelledBy;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function isRefundRequested(): bool
    {
        return true;
    }
}
