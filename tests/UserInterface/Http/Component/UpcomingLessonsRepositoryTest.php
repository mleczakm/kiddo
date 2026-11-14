<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class UpcomingLessonsRepositoryTest extends UpcomingLessonsComponentTestCase
{
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
                    static fn(\DateTimeInterface $startDate) => (
                        $startDate->format('Y-m-d') === $expectedStartDate->format('Y-m-d')
                    ),
                ),
                static::callback(
                    static fn(\DateTimeInterface $endDate) => (
                        $endDate->format('Y-m-d') === $expectedEndDate->format('Y-m-d')
                    ),
                ),
                true,
            )
            ->willReturn([]);

        $this->component->getLessons();
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
}
