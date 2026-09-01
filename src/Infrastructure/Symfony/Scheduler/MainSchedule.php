<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Scheduler;

use App\Application\Command\BackfillLegacyOrders;
use App\Application\Command\CheckBookingsToMarkPast;
use App\Application\Command\CheckExpiredBookings;
use App\Application\Command\CheckExpiredPayments;
use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\Command\PurgeOldNotifications;
use App\Application\Command\SampleResourceUsage;
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

    #[\Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::every('5 minutes', new CallbackMessageProvider(static fn() => [new CheckExpiredPayments()])),
                RecurringMessage::every('60 minutes', new CallbackMessageProvider(static fn() => [new CheckExpiredBookings()])),
                RecurringMessage::every(30, new ImportTransfersFromMail()),
                // Container memory/fd/socket sample -> Sentry metrics + JSON log line, so a
                // slow leak between restarts is a time series, not just the last few /health
                // probes. See App\Application\CommandHandler\SampleResourceUsageHandler.
                RecurringMessage::every(60, new SampleResourceUsage()),
                RecurringMessage::cron(
                    '45 8 * * *',
                    new CallbackMessageProvider(static fn() => [new DailyLessonsReminder()]),
                    new \DateTimeZone('Europe/Warsaw'),
                ),
                RecurringMessage::every(60, new TriggerMatchPaymentForTransferForPastTransfers()),
                RecurringMessage::cron('5 * * * *', new CheckBookingsToMarkPast(), new \DateTimeZone('Europe/Warsaw')),
                RecurringMessage::cron('15 3 * * *', new PurgeOldNotifications(), new \DateTimeZone('Europe/Warsaw')),
                RecurringMessage::cron('35 3 * * *', new BackfillLegacyOrders(100), new \DateTimeZone('Europe/Warsaw')),
            );
    }
}
