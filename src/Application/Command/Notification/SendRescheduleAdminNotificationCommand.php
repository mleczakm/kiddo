<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;

readonly class SendRescheduleAdminNotificationCommand
{
    public function __construct(
        public Booking $booking,
        public Lesson $oldLesson,
        public Lesson $newLesson,
        public User $rescheduledBy,
        public ?string $reason = null,
    ) {}
}
