<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Entity\Booking;

final readonly class AddBooking
{
    public function __construct(
        public Booking $booking
    ) {}
}
