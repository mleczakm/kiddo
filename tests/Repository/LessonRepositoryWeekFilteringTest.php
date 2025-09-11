<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class LessonRepositoryWeekFilteringTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private LessonRepository $lessonRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->lessonRepository = self::getContainer()->get(LessonRepository::class);

        // Clean up before tests
        $this->entityManager->createQuery('DELETE FROM App\Entity\Lesson')
            ->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Clean up after tests
        $this->entityManager->createQuery('DELETE FROM App\Entity\Lesson')
            ->execute();
        parent::tearDown();
    }

    public function testFindUpcomingWithBookingsInRange(): void
    {
        // Create test lessons
        $startDate = new \DateTimeImmutable('2024-01-01 10:00:00');
        $midDate = new \DateTimeImmutable('2024-01-03 10:00:00');
        $endDate = new \DateTimeImmutable('2024-01-05 10:00:00');
        $outsideDate = new \DateTimeImmutable('2024-01-10 10:00:00');

        $lesson1 = $this->createLesson('Lesson 1', $startDate);
        $lesson2 = $this->createLesson('Lesson 2', $midDate);
        $lesson3 = $this->createLesson('Lesson 3', $endDate);
        $lesson4 = $this->createLesson('Lesson 4', $outsideDate);

        $this->entityManager->persist($lesson1);
        $this->entityManager->persist($lesson2);
        $this->entityManager->persist($lesson3);
        $this->entityManager->persist($lesson4);
        $this->entityManager->flush();

        // Test range filtering
        $rangeStart = new \DateTimeImmutable('2024-01-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2024-01-05 23:59:59');

        $result = $this->lessonRepository->findUpcomingWithBookingsInRange($rangeStart, $rangeEnd);

        $this->assertCount(3, $result);
        $this->assertEquals('Lesson 1', $result[0]->getMetadata()->title);
        $this->assertEquals('Lesson 2', $result[1]->getMetadata()->title);
        $this->assertEquals('Lesson 3', $result[2]->getMetadata()->title);
    }

    public function testFindUpcomingInRange(): void
    {
        // Create test lessons
        $startDate = new \DateTimeImmutable('2024-02-01 10:00:00');
        $midDate = new \DateTimeImmutable('2024-02-03 10:00:00');
        $endDate = new \DateTimeImmutable('2024-02-05 10:00:00');
        $outsideDate = new \DateTimeImmutable('2024-02-10 10:00:00');

        $lesson1 = $this->createLesson('Range Lesson 1', $startDate);
        $lesson2 = $this->createLesson('Range Lesson 2', $midDate);
        $lesson3 = $this->createLesson('Range Lesson 3', $endDate);
        $lesson4 = $this->createLesson('Range Lesson 4', $outsideDate);

        $this->entityManager->persist($lesson1);
        $this->entityManager->persist($lesson2);
        $this->entityManager->persist($lesson3);
        $this->entityManager->persist($lesson4);
        $this->entityManager->flush();

        // Test range filtering
        $rangeStart = new \DateTimeImmutable('2024-02-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2024-02-05 23:59:59');

        $result = $this->lessonRepository->findUpcomingInRange($rangeStart, $rangeEnd);

        $this->assertCount(3, $result);
        $this->assertEquals('Range Lesson 1', $result[0]->getMetadata()->title);
        $this->assertEquals('Range Lesson 2', $result[1]->getMetadata()->title);
        $this->assertEquals('Range Lesson 3', $result[2]->getMetadata()->title);
    }

    public function testFindUpcomingWithBookingsInRangeReturnsEmptyWhenNoLessonsInRange(): void
    {
        // Create lesson outside range
        $outsideDate = new \DateTimeImmutable('2024-03-15 10:00:00');
        $lesson = $this->createLesson('Outside Lesson', $outsideDate);

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // Test range that doesn't include the lesson
        $rangeStart = new \DateTimeImmutable('2024-03-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2024-03-05 23:59:59');

        $result = $this->lessonRepository->findUpcomingWithBookingsInRange($rangeStart, $rangeEnd);

        $this->assertCount(0, $result);
    }

    public function testFindUpcomingInRangeOrdersByScheduleAsc(): void
    {
        // Create lessons in reverse chronological order
        $date3 = new \DateTimeImmutable('2024-04-03 10:00:00');
        $date1 = new \DateTimeImmutable('2024-04-01 10:00:00');
        $date2 = new \DateTimeImmutable('2024-04-02 10:00:00');

        $lesson3 = $this->createLesson('Third Lesson', $date3);
        $lesson1 = $this->createLesson('First Lesson', $date1);
        $lesson2 = $this->createLesson('Second Lesson', $date2);

        $this->entityManager->persist($lesson3);
        $this->entityManager->persist($lesson1);
        $this->entityManager->persist($lesson2);
        $this->entityManager->flush();

        // Test ordering
        $rangeStart = new \DateTimeImmutable('2024-04-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2024-04-03 23:59:59');

        $result = $this->lessonRepository->findUpcomingInRange($rangeStart, $rangeEnd);

        $this->assertCount(3, $result);
        $this->assertEquals('First Lesson', $result[0]->getMetadata()->title);
        $this->assertEquals('Second Lesson', $result[1]->getMetadata()->title);
        $this->assertEquals('Third Lesson', $result[2]->getMetadata()->title);
    }

    private function createLesson(string $title, \DateTimeImmutable $schedule): Lesson
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Test lead',
            visualTheme: 'rgb(255, 255, 255)',
            description: 'Test description',
            capacity: 10,
            schedule: $schedule,
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Test Category'
        );

        return new Lesson($metadata);
    }
}
