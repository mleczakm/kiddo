<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use DateTimeImmutable;
use Symfony\Component\Clock\Clock;

final readonly class DailyLessonsReminder
{
    public DateTimeImmutable $date;

    public function __construct(?DateTimeImmutable $date = null)
    {
        $this->date = $date ?? Clock::get()->now();
    }
}
