<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use App\Entity\Booking;

readonly class SendBookingCancellationNotificationCommand
{
    public function __construct(
        public Booking $booking
    ) {}
}
