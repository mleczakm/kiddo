<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\DTO\LessonMap;
use PHPUnit\Framework\Attributes\Group;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Repository\LessonRepository;
use App\UserInterface\Http\Component\UpcomingAttendeesComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[Group('unit')]
class UpcomingAttendeesComponentTest extends TestCase
{
    private LessonRepository&MockObject $lessonRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private UpcomingAttendeesComponent $component;

    protected function setUp(): void
    {
        $this->lessonRepository = $this->createMock(LessonRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->component = new UpcomingAttendeesComponent($this->lessonRepository, $this->entityManager);
    }

    public function testDefaultWeekIsCurrentDate(): void
    {
        // Mock the clock to return a specific date
        $mockClock = new MockClock('2024-01-15 10:00:00');
        Clock::set($mockClock);

        $component = new UpcomingAttendeesComponent($this->lessonRepository, $this->entityManager);

        $this->assertEquals('2024-01-15', $component->week);
    }

    public function testGetLessonsCallsRepositoryWithCorrectDateRange(): void
    {
        $this->component->week = '2024-01-15';

        $expectedStartDate = new \DateTimeImmutable('2024-01-15');
        $expectedEndDate = new \DateTimeImmutable('2024-01-22'); // +7 days

        $this->lessonRepository
            ->expects($this->once())
            ->method('findUpcomingWithBookingsInRange')
            ->with(
                $this->callback(fn($startDate) => $startDate->format('Y-m-d') === $expectedStartDate->format('Y-m-d')),
                $this->callback(fn($endDate) => $endDate->format('Y-m-d') === $expectedEndDate->format('Y-m-d'))
            )
            ->willReturn([]);

        $this->component->getLessons();
    }

    public function testGetWeekStartReturnsCorrectDate(): void
    {
        $this->component->week = '2024-01-15';

        $weekStart = $this->component->getWeekStart();

        $this->assertEquals('2024-01-15', $weekStart->format('Y-m-d'));
    }

    public function testGetWeekEndReturnsCorrectDate(): void
    {
        $this->component->week = '2024-01-15';

        $weekEnd = $this->component->getWeekEnd();

        $this->assertEquals('2024-01-22', $weekEnd->format('Y-m-d'));
    }

    public function testGetLessonsReturnsRepositoryResults(): void
    {
        $expectedLessons = [$this->createMockLesson('Test Lesson 1'), $this->createMockLesson('Test Lesson 2')];

        $this->lessonRepository
            ->method('findUpcomingWithBookingsInRange')
            ->willReturn($expectedLessons);

        $result = $this->component->getLessons();

        $this->assertSame($expectedLessons, $result);
    }

    public function testIncreaseCapacityUpdatesLessonAndFlushes(): void
    {
        $lesson = $this->createMockLesson('Test Lesson');
        $metadata = $lesson->getMetadata();
        $metadata->capacity = 10;

        $this->lessonRepository
            ->expects($this->once())
            ->method('find')
            ->with('lesson-id')
            ->willReturn($lesson);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->component->increaseCapacity('lesson-id');

        $this->assertEquals(11, $metadata->capacity);
    }

    public function testDecreaseCapacityUpdatesLessonWhenValid(): void
    {
        $lesson = $this->createMockLesson('Test Lesson');
        $metadata = $lesson->getMetadata();
        $metadata->capacity = 10;

        // Mock that lesson has 5 bookings (less than capacity)
        $bookingsCollection = $this->createMock(Collection::class);
        $bookingsCollection->method('count')
            ->willReturn(5);
        $lesson->method('getBookings')
            ->willReturn($bookingsCollection);

        $this->lessonRepository
            ->expects($this->once())
            ->method('find')
            ->with('lesson-id')
            ->willReturn($lesson);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->component->decreaseCapacity('lesson-id');

        $this->assertEquals(9, $metadata->capacity);
    }

    public function testDecreaseCapacityDoesNotUpdateWhenCapacityEqualsActiveBookings(): void
    {
        $metadata = new LessonMetadata(
            title: 'Test Lesson',
            lead: 'Test lead',
            visualTheme: 'rgb(255, 255, 255)',
            description: 'Test description',
            capacity: 5,
            schedule: new \DateTimeImmutable(),
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Test Category'
        );

        $lesson = $this->createMock(Lesson::class);
        $lesson->method('getMetadata')
            ->willReturn($metadata);

        // Mock that lesson has 5 active bookings (equals capacity)
        $lesson->method('getAvailableSpots')
            ->willReturn(5);

        $this->lessonRepository
            ->expects($this->once())
            ->method('find')
            ->with('lesson-id')
            ->willReturn($lesson);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->component->decreaseCapacity('lesson-id');

        $this->assertEquals(5, $metadata->capacity);
    }

    public function testToggleCancelledChangesShowCancelledState(): void
    {
        $this->assertFalse($this->component->showCancelled);

        $this->component->toggleCancelled();

        $this->assertTrue($this->component->showCancelled);
    }

    public function testGetLessonsCallsRepositoryWithShowCancelledParameter(): void
    {
        $this->component->week = '2024-01-15';
        $this->component->showCancelled = true;

        $expectedStartDate = new \DateTimeImmutable('2024-01-15');
        $expectedEndDate = new \DateTimeImmutable('2024-01-22');

        $this->lessonRepository
            ->expects($this->once())
            ->method('findUpcomingWithBookingsInRange')
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

    private function createMockLesson(string $title): Lesson&MockObject
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Test lead',
            visualTheme: 'rgb(255, 255, 255)',
            description: 'Test description',
            capacity: 10,
            schedule: new \DateTimeImmutable(),
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Test Category'
        );

        $lesson = $this->createMock(Lesson::class);
        $lesson->method('getMetadata')
            ->willReturn($metadata);
        $lesson->method('getBookings')
            ->willReturn(new ArrayCollection());

        return $lesson;
    }
}
