<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\User;
use Symfony\Component\Uid\Ulid;

final readonly class ReactivateBooking
{
    public function __construct(
        private Ulid $bookingId,
        private User $reactivatedBy,
        private ?string $reason = null,
    ) {}

    public function getBookingId(): Ulid
    {
        return $this->bookingId;
    }

    public function getReactivatedBy(): User
    {
        return $this->reactivatedBy;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
