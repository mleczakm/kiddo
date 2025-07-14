<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Scheduler;

use App\Application\Command\CheckExpiredBookings;
use App\Application\Command\CheckExpiredPayments;
use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\Command\TriggerMatchPaymentForTransferForPastTransfers;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->with(RecurringMessage::every('5 minutes', new CheckExpiredPayments(expirationMinutes: 30)))
            ->with(RecurringMessage::every('30 minutes', new CheckExpiredBookings()))
            ->with(RecurringMessage::every(29, new ImportTransfersFromMail()))
            ->with(RecurringMessage::cron('0 7 * * *', new DailyLessonsReminder()))
            ->with(RecurringMessage::every(60, new TriggerMatchPaymentForTransferForPastTransfers()))
        ;
    }
}
