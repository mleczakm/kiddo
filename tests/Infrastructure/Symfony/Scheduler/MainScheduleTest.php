<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony\Scheduler;

use PHPUnit\Framework\Attributes\Group;
use App\Infrastructure\Symfony\Scheduler\MainSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;

#[Group('unit')]
class MainScheduleTest extends TestCase
{
    public function testCreateSchedule(): void
    {
        $schedule = new MainSchedule(new NullAdapter())
            ->getSchedule();

        self::assertCount(6, $schedule->getRecurringMessages());
    }
}
