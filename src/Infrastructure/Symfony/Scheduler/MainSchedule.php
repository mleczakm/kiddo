<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Scheduler;

use App\Application\Command\CheckExpiredPayments;
use App\Application\Command\ImportTransfersFromMail;
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
            ->with(RecurringMessage::every('30 minutes', new ImportTransfersFromMail()))
        ;
    }
}
