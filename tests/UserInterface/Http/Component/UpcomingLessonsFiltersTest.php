<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Lesson;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class UpcomingLessonsFiltersTest extends UpcomingLessonsTestCase
{
    public function testGetUserBookingsByLessonReturnsEmptyWhenGuest(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->bookingRepository->expects($this->never())->method('findForUserAndLessons');

        static::assertSame([], $this->component->getUserBookingsByLesson());
    }

    public function testGetWorkshopsByDayReturnsSevenDays(): void
    {
        $this->component->week = '2024-02-19';
        $this->lessonRepository->method('findByFilters')->willReturn([]);

        $days = $this->component->getWorkshopsByDay();

        static::assertCount(7, $days);
        static::assertSame('2024-02-19', $days[0]['date']);
        static::assertSame('2024-02-25', $days[6]['date']);
    }

    public function testDefaultShowSearchIsTrue(): void
    {
        static::assertTrue($this->component->showSearch);
    }

    public function testCanSetLimit(): void
    {
        $this->component->limit = 5;
        static::assertSame(5, $this->component->limit);
    }

    public function testCanSetQueryProperty(): void
    {
        $this->component->query = 'test search';
        static::assertSame('test search', $this->component->query);
    }

    public function testCanSetAgeProperty(): void
    {
        $this->component->age = 7;
        static::assertSame(7, $this->component->age);
    }

    public function testGetWorkshopsCallsRepositoryWithCorrectParameters(): void
    {
        $this->component->query = 'test query';
        $this->component->age = 5;
        $this->component->week = '2024-02-20';
        $this->component->limit = 3;

        $this->lessonRepository
            ->expects($this->once())
            ->method('findByFilters')
            ->with('test query', 5, '2024-02-20', 3)
            ->willReturn([]);

        $this->component->getWorkshops();
    }

    public function testGetWorkshopsWithNullValues(): void
    {
        $this->component->query = null;
        $this->component->age = null;
        $this->component->week = '2024-02-20';
        $this->component->limit = null;

        $this->lessonRepository
            ->expects($this->once())
            ->method('findByFilters')
            ->with(null, null, '2024-02-20', null)
            ->willReturn([]);

        $this->component->getWorkshops();
    }

    public function testGetWorkshopsReturnsRepositoryResults(): void
    {
        $expectedLessons = [
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
        ];

        $this->lessonRepository->method('findByFilters')->willReturn($expectedLessons);

        $result = $this->component->getWorkshops();

        static::assertSame($expectedLessons, $result);
        static::assertCount(3, $result);
    }

    public function testGetWorkshopsWithLimit(): void
    {
        $this->component->limit = 2;

        $mockLessons = [$this->createMock(Lesson::class), $this->createMock(Lesson::class)];

        $this->lessonRepository
            ->expects($this->once())
            ->method('findByFilters')
            ->with(null, null, $this->component->week, 2)
            ->willReturn($mockLessons);

        $result = $this->component->getWorkshops();

        static::assertCount(2, $result);
    }
}
