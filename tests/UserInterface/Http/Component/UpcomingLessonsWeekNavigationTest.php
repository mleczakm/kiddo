<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\UserInterface\Http\Component\UpcomingLessonsComponent;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[Group('unit')]
final class UpcomingLessonsWeekNavigationTest extends UpcomingLessonsComponentTestCase
{
    public function testDefaultWeekIsCurrentDate(): void
    {
        // Mock the clock to return a specific date
        $mockClock = new MockClock('2024-02-20 14:30:00');
        Clock::set($mockClock);

        $component = new UpcomingLessonsComponent($this->lessonRepository);

        static::assertSame('2024-02-20', $component->week);
    }

    public function testGetWeekStartReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekStart = $this->component->getWeekStart();

        static::assertSame('2024-03-10', $weekStart->format('Y-m-d'));
    }

    public function testGetWeekEndReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekEnd = $this->component->getWeekEnd();

        static::assertSame('2024-03-17', $weekEnd->format('Y-m-d'));
    }

    public function testWeekNavigationCalculatesCorrectDates(): void
    {
        // Test week navigation functionality
        $testCases = [
            ['2024-01-01', '2024-01-01', '2024-01-08'], // New Year's Day
            ['2024-06-15', '2024-06-15', '2024-06-22'], // Mid-year
            ['2024-12-25', '2024-12-25', '2025-01-01'], // Christmas, crossing year boundary
        ];

        foreach ($testCases as [$weekDate, $expectedStart, $expectedEnd]) {
            $this->component->week = $weekDate;

            $weekStart = $this->component->getWeekStart();
            $weekEnd = $this->component->getWeekEnd();

            static::assertEquals(
                $expectedStart,
                $weekStart->format('Y-m-d'),
                "Week start failed for date: {$weekDate}",
            );
            static::assertEquals($expectedEnd, $weekEnd->format('Y-m-d'), "Week end failed for date: {$weekDate}");
        }
    }
}
