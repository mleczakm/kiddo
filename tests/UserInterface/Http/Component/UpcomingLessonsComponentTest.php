<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\UserInterface\Http\Component\UpcomingLessonsComponent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

#[Group('unit')]
class UpcomingLessonsComponentTest extends TestCase
{
    private LessonRepository&MockObject $lessonRepository;

    private UpcomingLessonsComponent $component;

    #[\Override]
    protected function setUp(): void
    {
        $this->lessonRepository = $this->createMock(LessonRepository::class);

        $this->component = new UpcomingLessonsComponent($this->lessonRepository);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testDefaultWeekIsCurrentDate(): void
    {
        // Mock the clock to return a specific date
        $mockClock = new MockClock('2024-02-20 14:30:00');
        Clock::set($mockClock);

        $component = new UpcomingLessonsComponent($this->lessonRepository);

        static::assertSame('2024-02-20', $component->week);
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
                static::callback(
                    static fn($startDate) => $startDate->format('Y-m-d') === $expectedStartDate->format('Y-m-d'),
                ),
                static::callback(
                    static fn($endDate) => $endDate->format('Y-m-d') === $expectedEndDate->format('Y-m-d'),
                ),
                true,
            )
            ->willReturn([]);

        $this->component->getLessons();
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

    public function testGetLessonsReturnsRepositoryResults(): void
    {
        $expectedLessons = [
            $this->createMockLesson('Upcoming Lesson 1'),
            $this->createMockLesson('Upcoming Lesson 2'),
            $this->createMockLesson('Upcoming Lesson 3'),
        ];

        $this->lessonRepository->method('findUpcomingInRange')->willReturn($expectedLessons);

        $result = $this->component->getLessons();

        static::assertSame($expectedLessons, $result);
        static::assertCount(3, $result);
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

    public function testGetLessonsWithEmptyRepository(): void
    {
        $this->lessonRepository->method('findUpcomingInRange')->willReturn([]);

        $result = $this->component->getLessons();

        static::assertCount(0, $result);
    }

    public function testGetLessonsAlwaysRequestsCancelledLessons(): void
    {
        $this->component->week = '2024-02-20';

        // Cancelled lessons are always fetched; the template groups them into
        // the shared "inactive reservations" disclosure.
        $this->lessonRepository
            ->expects($this->once())
            ->method('findUpcomingInRange')
            ->with(static::anything(), static::anything(), true)
            ->willReturn([]);

        $this->component->getLessons();
    }

    private function createMockLesson(string $title): Lesson
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Test lead for upcoming lesson',
            visualTheme: 'rgb(200, 255, 200)',
            description: 'Test description for upcoming lesson',
            capacity: 15,
            duration: 90,
            ageRange: new AgeRange(2, 5),
            category: 'Test Category',
        );

        $lesson = $this->createMock(Lesson::class);
        $lesson->method('getMetadata')->willReturn($metadata);
        $lesson->method('isPubliclyVisible')->willReturn(true);
        $lesson->schedule = new \DateTimeImmutable();

        return $lesson;
    }
}
