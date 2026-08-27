<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use Aeon\Calendar\TimeUnit;
use App\Application\Service\WorkingDaysDeadlineCalculator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class WorkingDaysDeadlineCalculatorTest extends TestCase
{
    #[Test]
    public function midWeekTwentyFourHoursStaysWithinTheSameWorkingWeek(): void
    {
        $calculator = new WorkingDaysDeadlineCalculator();

        // Wednesday 2024-01-03 10:00 UTC
        $from = new \DateTimeImmutable('2024-01-03 10:00:00', new \DateTimeZone('UTC'));

        $deadline = $calculator->addWorkingTime($from, TimeUnit::hours(24));

        static::assertSame('2024-01-04 10:00:00', $deadline->format('Y-m-d H:i:s'), 'Thursday, same time');
    }

    #[Test]
    public function fridayEveningTwentyFourHoursSkipsTheWeekend(): void
    {
        $calculator = new WorkingDaysDeadlineCalculator();

        // Friday 2024-01-05 22:45 UTC
        $from = new \DateTimeImmutable('2024-01-05 22:45:00', new \DateTimeZone('UTC'));

        $deadline = $calculator->addWorkingTime($from, TimeUnit::hours(24));

        static::assertSame(
            '2024-01-08 22:45:00',
            $deadline->format('Y-m-d H:i:s'),
            'Monday, same time - weekend does not count',
        );
    }

    #[Test]
    public function startingOnAWeekendDoesNotCountUntilTheNextWorkingDay(): void
    {
        $calculator = new WorkingDaysDeadlineCalculator();

        // Saturday 2024-01-06 15:00 UTC
        $from = new \DateTimeImmutable('2024-01-06 15:00:00', new \DateTimeZone('UTC'));

        $deadline = $calculator->addWorkingTime($from, TimeUnit::hours(24));

        static::assertSame(
            '2024-01-09 00:00:00',
            $deadline->format('Y-m-d H:i:s'),
            'Weekend contributes nothing; the clock starts Monday midnight',
        );
    }

    #[Test]
    public function sundayAlsoDoesNotCount(): void
    {
        $calculator = new WorkingDaysDeadlineCalculator();

        // Sunday 2024-01-07 08:00 UTC
        $from = new \DateTimeImmutable('2024-01-07 08:00:00', new \DateTimeZone('UTC'));

        $deadline = $calculator->addWorkingTime($from, TimeUnit::hours(24));

        static::assertSame('2024-01-09 00:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function polishPublicHolidayDoesNotCountTowardTheDeadline(): void
    {
        $calculator = new WorkingDaysDeadlineCalculator();

        // Friday 2025-01-03 12:00 UTC. Monday 2025-01-06 is Epiphany, a Polish
        // public holiday, so 24 working hours land on Tuesday: 12h on Friday
        // plus 12h once Tuesday starts.
        $from = new \DateTimeImmutable('2025-01-03 12:00:00', new \DateTimeZone('UTC'));

        $deadline = $calculator->addWorkingTime($from, TimeUnit::hours(24));

        static::assertSame('2025-01-07 12:00:00', $deadline->format('Y-m-d H:i:s'));
    }
}
