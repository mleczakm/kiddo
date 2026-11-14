<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\UserInterface\Http\Component\UpcomingLessons;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[Group('unit')]
final class UpcomingLessonsCalendarTest extends UpcomingLessonsTestCase
{
    public function testDefaultWeekIsCurrentDate(): void
    {
        $mockClock = new MockClock('2024-02-20 14:30:00');
        Clock::set($mockClock);

        $component = new UpcomingLessons($this->lessonRepository, $this->bookingRepository, $this->security);

        static::assertSame('2024-02-20', $component->week);
    }

    public function testCanSetWeekProperty(): void
    {
        $this->component->week = '2024-05-15';
        static::assertSame('2024-05-15', $this->component->week);
    }

    public function testGetCurrentWeekReturnsCurrentDate(): void
    {
        $mockClock = new MockClock('2024-03-15 10:30:00');
        Clock::set($mockClock);

        $result = $this->component->getCurrentWeek();

        static::assertSame('2024-03-15', $result);
    }

    public function testGetCurrentWeekWithDifferentDates(): void
    {
        $mockClock = new MockClock('2024-12-31 23:59:59');
        Clock::set($mockClock);

        $result = $this->component->getCurrentWeek();

        static::assertSame('2024-12-31', $result);
    }

    public function testGetWeekStartReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekStart = $this->component->getWeekStart();

        static::assertSame('2024-03-10', $weekStart->format('Y-m-d'));
        static::assertInstanceOf(\DateTimeImmutable::class, $weekStart);
    }

    public function testGetWeekEndReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekEnd = $this->component->getWeekEnd();

        static::assertSame('2024-03-17', $weekEnd->format('Y-m-d'));
        static::assertInstanceOf(\DateTimeImmutable::class, $weekEnd);
    }

    public function testWeekNavigationCalculatesCorrectDates(): void
    {
        $this->component->week = '2024-02-20';

        // Test week start
        $weekStart = $this->component->getWeekStart();
        static::assertSame('2024-02-20', $weekStart->format('Y-m-d'));

        // Test week end (should be 7 days later)
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2024-02-27', $weekEnd->format('Y-m-d'));
    }

    public function testWeekNavigationWithDifferentDates(): void
    {
        // Test with end of month
        $this->component->week = '2024-02-28';
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2024-03-06', $weekEnd->format('Y-m-d'));

        // Test with year boundary
        $this->component->week = '2024-12-30';
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2025-01-06', $weekEnd->format('Y-m-d'));

        // Test with leap year
        $this->component->week = '2024-02-26'; // 2024 is a leap year
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2024-03-04', $weekEnd->format('Y-m-d'));
    }
}
