<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use Symfony\Component\Uid\Ulid;

readonly class SendBookingCancellationNotificationCommand
{
    public function __construct(
        public Ulid $bookingId,
    ) {}
}
