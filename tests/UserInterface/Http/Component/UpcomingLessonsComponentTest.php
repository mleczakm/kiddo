<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Repository\LessonRepository;
use App\UserInterface\Http\Component\UpcomingLessonsComponent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[Group('unit')]
class UpcomingLessonsComponentTest extends TestCase
{
    private LessonRepository&MockObject $lessonRepository;

    private UpcomingLessonsComponent $component;

    protected function setUp(): void
    {
        $this->lessonRepository = $this->createMock(LessonRepository::class);

        $this->component = new UpcomingLessonsComponent($this->lessonRepository);
    }

    public function testDefaultWeekIsCurrentDate(): void
    {
        // Mock the clock to return a specific date
        $mockClock = new MockClock('2024-02-20 14:30:00');
        Clock::set($mockClock);

        $component = new UpcomingLessonsComponent($this->lessonRepository);

        $this->assertEquals('2024-02-20', $component->week);
    }

    public function testGetLessonsCallsRepositoryWithCorrectDateRange(): void
    {
        $this->component->week = '2024-02-20';

        $expectedStartDate = new \DateTimeImmutable('2024-02-20');
        $expectedEndDate = new \DateTimeImmutable('2024-02-27'); // +7 days

        $this->lessonRepository
            ->expects($this->once())
            ->method('findUpcomingInRange')
            ->with(
                $this->callback(fn($startDate) => $startDate->format('Y-m-d') === $expectedStartDate->format('Y-m-d')),
                $this->callback(fn($endDate) => $endDate->format('Y-m-d') === $expectedEndDate->format('Y-m-d'))
            )
            ->willReturn([]);

        $this->component->getLessons();
    }

    public function testGetWeekStartReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekStart = $this->component->getWeekStart();

        $this->assertEquals('2024-03-10', $weekStart->format('Y-m-d'));
    }

    public function testGetWeekEndReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekEnd = $this->component->getWeekEnd();

        $this->assertEquals('2024-03-17', $weekEnd->format('Y-m-d'));
    }

    public function testGetLessonsReturnsRepositoryResults(): void
    {
        $expectedLessons = [
            $this->createMockLesson('Upcoming Lesson 1'),
            $this->createMockLesson('Upcoming Lesson 2'),
            $this->createMockLesson('Upcoming Lesson 3'),
        ];

        $this->lessonRepository
            ->method('findUpcomingInRange')
            ->willReturn($expectedLessons);

        $result = $this->component->getLessons();

        $this->assertSame($expectedLessons, $result);
        $this->assertCount(3, $result);
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

            $this->assertEquals(
                $expectedStart,
                $weekStart->format('Y-m-d'),
                "Week start failed for date: {$weekDate}"
            );
            $this->assertEquals($expectedEnd, $weekEnd->format('Y-m-d'), "Week end failed for date: {$weekDate}");
        }
    }

    public function testGetLessonsWithEmptyRepository(): void
    {
        $this->lessonRepository
            ->method('findUpcomingInRange')
            ->willReturn([]);

        $result = $this->component->getLessons();

        $this->assertCount(0, $result);
    }

    public function testToggleCancelledChangesShowCancelledState(): void
    {
        $this->assertFalse($this->component->showCancelled);

        $this->component->toggleCancelled();

        $this->assertTrue($this->component->showCancelled);
    }

    public function testGetLessonsCallsRepositoryWithShowCancelledParameter(): void
    {
        $this->component->week = '2024-02-20';
        $this->component->showCancelled = true;

        $expectedStartDate = new \DateTimeImmutable('2024-02-20');
        $expectedEndDate = new \DateTimeImmutable('2024-02-27');

        $this->lessonRepository
            ->expects($this->once())
            ->method('findUpcomingInRange')
            ->with(
                $this->callback(fn($startDate) => $startDate->format('Y-m-d') === $expectedStartDate->format('Y-m-d')),
                $this->callback(fn($endDate) => $endDate->format('Y-m-d') === $expectedEndDate->format('Y-m-d')),
                true // showCancelled = true
            )
            ->willReturn([]);

        $this->component->getLessons();
    }

    public function testDefaultShowCancelledIsFalse(): void
    {
        $this->assertFalse($this->component->showCancelled);
    }

    private function createMockLesson(string $title): Lesson
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Test lead for upcoming lesson',
            visualTheme: 'rgb(200, 255, 200)',
            description: 'Test description for upcoming lesson',
            capacity: 15,
            schedule: new \DateTimeImmutable(),
            duration: 90,
            ageRange: new AgeRange(2, 5),
            category: 'Test Category'
        );

        $lesson = $this->createMock(Lesson::class);
        $lesson->method('getMetadata')
            ->willReturn($metadata);

        return $lesson;
    }
}
