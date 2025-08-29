<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\UserInterface\Http\Component\UpcomingAttendeesComponent;
use App\UserInterface\Http\Component\UpcomingLessonsComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WeekFilteringIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private UpcomingAttendeesComponent $attendeesComponent;

    private UpcomingLessonsComponent $lessonsComponent;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Get the real components from the container
        $this->attendeesComponent = self::getContainer()->get(UpcomingAttendeesComponent::class);
        $this->lessonsComponent = self::getContainer()->get(UpcomingLessonsComponent::class);

        // Clean up before tests
        $this->entityManager->createQuery('DELETE FROM App\Entity\Lesson')
            ->execute();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        $this->entityManager->createQuery('DELETE FROM App\Entity\Lesson')
            ->execute();
        parent::tearDown();
    }

    public function testWeekFilteringWorksEndToEnd(): void
    {
        // Create test lessons spanning different weeks
        $lessons = [
            $this->createLesson('Week 1 Lesson 1', new \DateTimeImmutable('2024-01-01 10:00:00')),
            $this->createLesson('Week 1 Lesson 2', new \DateTimeImmutable('2024-01-03 14:00:00')),
            $this->createLesson('Week 1 Lesson 3', new \DateTimeImmutable('2024-01-07 16:00:00')),
            $this->createLesson('Week 2 Lesson 1', new \DateTimeImmutable('2024-01-08 10:00:00')),
            $this->createLesson('Week 2 Lesson 2', new \DateTimeImmutable('2024-01-10 14:00:00')),
            $this->createLesson('Outside Range', new \DateTimeImmutable('2024-01-20 10:00:00')),
        ];

        foreach ($lessons as $lesson) {
            $this->entityManager->persist($lesson);
        }
        $this->entityManager->flush();

        // Test UpcomingAttendeesComponent week filtering
        $this->attendeesComponent->week = '2024-01-01';
        $attendeesResults = $this->attendeesComponent->getLessons();

        $this->assertCount(3, $attendeesResults, 'Should find 3 lessons in first week range (Jan 1-7)');

        $titles = array_map(fn($lesson) => $lesson->getMetadata()->title, $attendeesResults);
        $this->assertContains('Week 1 Lesson 1', $titles);
        $this->assertContains('Week 1 Lesson 2', $titles);
        $this->assertContains('Week 1 Lesson 3', $titles);

        // Test UpcomingLessonsComponent week filtering
        $this->lessonsComponent->week = '2024-01-08';
        $lessonsResults = $this->lessonsComponent->getLessons();

        $this->assertCount(2, $lessonsResults, 'Should find 2 lessons in second week range (Jan 8-14)');

        $titles = array_map(fn($lesson) => $lesson->getMetadata()->title, $lessonsResults);
        $this->assertContains('Week 2 Lesson 1', $titles);
        $this->assertContains('Week 2 Lesson 2', $titles);
    }

    public function testWeekNavigationDatesAreCorrect(): void
    {
        // Test various week start dates and their corresponding end dates
        $testCases = [
            ['2024-01-01', '2024-01-08'],  // Monday to next Monday
            ['2024-06-15', '2024-06-22'],  // Saturday to next Saturday
            ['2024-12-31', '2025-01-07'],  // New Year boundary
        ];

        foreach ($testCases as [$startWeek, $expectedEndWeek]) {
            // Test UpcomingAttendeesComponent
            $this->attendeesComponent->week = $startWeek;
            $attendeesStart = $this->attendeesComponent->getWeekStart();
            $attendeesEnd = $this->attendeesComponent->getWeekEnd();

            $this->assertEquals($startWeek, $attendeesStart->format('Y-m-d'));
            $this->assertEquals($expectedEndWeek, $attendeesEnd->format('Y-m-d'));

            // Test UpcomingLessonsComponent
            $this->lessonsComponent->week = $startWeek;
            $lessonsStart = $this->lessonsComponent->getWeekStart();
            $lessonsEnd = $this->lessonsComponent->getWeekEnd();

            $this->assertEquals($startWeek, $lessonsStart->format('Y-m-d'));
            $this->assertEquals($expectedEndWeek, $lessonsEnd->format('Y-m-d'));
        }
    }

    public function testEmptyRangeReturnsNoLessons(): void
    {
        // Create lesson outside the test range
        $lesson = $this->createLesson('Outside Range', new \DateTimeImmutable('2024-05-15 10:00:00'));
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // Test range that doesn't include any lessons
        $this->attendeesComponent->week = '2024-01-01';
        $attendeesResults = $this->attendeesComponent->getLessons();

        $this->lessonsComponent->week = '2024-01-01';
        $lessonsResults = $this->lessonsComponent->getLessons();

        $this->assertCount(0, $attendeesResults, 'UpcomingAttendeesComponent should return empty array');
        $this->assertCount(0, $lessonsResults, 'UpcomingLessonsComponent should return empty array');
    }

    public function testLessonsAreOrderedBySchedule(): void
    {
        // Create lessons in reverse chronological order
        $lessons = [
            $this->createLesson('Third', new \DateTimeImmutable('2024-03-03 16:00:00')),
            $this->createLesson('First', new \DateTimeImmutable('2024-03-01 10:00:00')),
            $this->createLesson('Second', new \DateTimeImmutable('2024-03-02 14:00:00')),
        ];

        foreach ($lessons as $lesson) {
            $this->entityManager->persist($lesson);
        }
        $this->entityManager->flush();

        $this->attendeesComponent->week = '2024-03-01';
        $results = $this->attendeesComponent->getLessons();

        $this->assertCount(3, $results);
        $this->assertEquals('First', $results[0]->getMetadata()->title);
        $this->assertEquals('Second', $results[1]->getMetadata()->title);
        $this->assertEquals('Third', $results[2]->getMetadata()->title);
    }

    private function createLesson(string $title, \DateTimeImmutable $schedule): Lesson
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Integration test lead',
            visualTheme: 'rgb(255, 255, 255)',
            description: 'Integration test description',
            capacity: 10,
            schedule: $schedule,
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Integration Test'
        );

        return new Lesson($metadata);
    }
}
