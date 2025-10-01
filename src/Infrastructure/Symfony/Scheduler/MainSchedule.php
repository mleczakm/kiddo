<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Scheduler;

use App\Application\Command\CheckBookingsToMarkPast;
use App\Application\Command\CheckExpiredBookings;
use App\Application\Command\CheckExpiredPayments;
use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\Command\TriggerMatchPaymentForTransferForPastTransfers;
use App\Infrastructure\Symfony\Messenger\TenantStamp;
use Symfony\Component\Messenger\Envelope;
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
            ->with(
                RecurringMessage::every(
                    '5 minutes',
                    Envelope::wrap(new CheckExpiredPayments(expirationMinutes: 24 * 60), [
                        new TenantStamp('warsztatowniasensoryczna.pl'),
                    ])
                ),
                RecurringMessage::every(
                    '60 minutes',
                    Envelope::wrap(new CheckExpiredBookings(), [new TenantStamp('warsztatowniasensoryczna.pl')])
                ),
                RecurringMessage::every(
                    30,
                    Envelope::wrap(new ImportTransfersFromMail(), [new TenantStamp('warsztatowniasensoryczna.pl')])
                ),
                RecurringMessage::cron(
                    '45 8 * * *',
                    Envelope::wrap(new DailyLessonsReminder(), [new TenantStamp('warsztatowniasensoryczna.pl')]),
                    new \DateTimeZone('Europe/Warsaw')
                ),
                RecurringMessage::every(
                    60,
                    Envelope::wrap(new TriggerMatchPaymentForTransferForPastTransfers(), [
                        new TenantStamp('warsztatowniasensoryczna.pl'),
                    ])
                ),
                RecurringMessage::cron(
                    '5 * * * *',
                    Envelope::wrap(new CheckBookingsToMarkPast(), [new TenantStamp('warsztatowniasensoryczna.pl')]),
                    new \DateTimeZone('Europe/Warsaw')
                ),
            );
    }
}
