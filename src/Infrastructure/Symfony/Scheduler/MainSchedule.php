<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Scheduler;

use App\Application\Command\CheckBookingsToMarkPast;
use App\Application\Command\CheckExpiredBookings;
use App\Application\Command\CheckExpiredPayments;
use App\Application\Command\ExtendSeriesSchedule;
use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\Command\TriggerMatchPaymentForTransferForPastTransfers;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\CallbackMessageProvider;
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
            ->with(
                RecurringMessage::every('5 minutes', new CheckExpiredPayments(expirationMinutes: 24 * 60)),
                RecurringMessage::every(
                    '60 minutes',
                    new CallbackMessageProvider(fn() => [new CheckExpiredBookings()]),
                ),
                RecurringMessage::every(30, new ImportTransfersFromMail()),
                RecurringMessage::cron(
                    '45 8 * * *',
                    new CallbackMessageProvider(fn() => [new DailyLessonsReminder()]),
                    new \DateTimeZone('Europe/Warsaw')
                ),
                RecurringMessage::every(60, new TriggerMatchPaymentForTransferForPastTransfers()),
                RecurringMessage::cron(
                    '5 * * * *',
                    new CheckBookingsToMarkPast(),
                    new \DateTimeZone('Europe/Warsaw')
                ),
                RecurringMessage::cron('1 0 * * *', new ExtendSeriesSchedule(), new \DateTimeZone('Europe/Warsaw')),
            );
    }
}
