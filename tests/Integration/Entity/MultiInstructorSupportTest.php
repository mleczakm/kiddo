<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Entity\User;
use App\Entity\WorkshopType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[\PHPUnit\Framework\Attributes\Group('functional')]
class MultiInstructorSupportTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private User $instructor1;

    private User $instructor2;

    private User $instructor3;

    protected function setUp(): void
    {
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        // Create test instructors
        $this->instructor1 = new User('instructor1@test.com', 'Instructor One');
        $this->instructor1->setRoles(['ROLE_INSTRUCTOR']);
        $this->entityManager->persist($this->instructor1);

        $this->instructor2 = new User('instructor2@test.com', 'Instructor Two');
        $this->instructor2->setRoles(['ROLE_INSTRUCTOR']);
        $this->entityManager->persist($this->instructor2);

        $this->instructor3 = new User('instructor3@test.com', 'Instructor Three');
        $this->instructor3->setRoles(['ROLE_INSTRUCTOR']);
        $this->entityManager->persist($this->instructor3);

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testSeriesCanHaveMultipleInstructors(): void
    {
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->addInstructor($this->instructor1);
        $series->addInstructor($this->instructor2);
        $this->entityManager->persist($series);
        $this->entityManager->flush();

        $this->assertCount(2, $series->getInstructors());
        $this->assertTrue($series->getInstructors()->contains($this->instructor1));
        $this->assertTrue($series->getInstructors()->contains($this->instructor2));
    }

    public function testInstructorCanBeRemovedFromSeries(): void
    {
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->addInstructor($this->instructor1);
        $series->addInstructor($this->instructor2);
        $this->entityManager->persist($series);
        $this->entityManager->flush();

        $this->assertCount(2, $series->getInstructors());

        $series->removeInstructor($this->instructor1);
        $this->entityManager->flush();

        $this->assertCount(1, $series->getInstructors());
        $this->assertFalse($series->getInstructors()->contains($this->instructor1));
        $this->assertTrue($series->getInstructors()->contains($this->instructor2));
    }

    public function testLessonCanHaveMultipleInstructors(): void
    {
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            schedule: Clock::get()->now()->modify('+1 day'),
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata);
        $lesson->addInstructor($this->instructor1);
        $lesson->addInstructor($this->instructor2);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $this->assertCount(2, $lesson->getInstructors());
        $this->assertTrue($lesson->getInstructors()->contains($this->instructor1));
        $this->assertTrue($lesson->getInstructors()->contains($this->instructor2));
    }

    public function testLessonCanHaveInstructorsFromSeries(): void
    {
        // Create series with instructors
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->addInstructor($this->instructor1);
        $series->addInstructor($this->instructor2);
        $this->entityManager->persist($series);

        // Create lesson with additional instructor
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            schedule: Clock::get()->now()->modify('+1 day'),
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata);
        $lesson->addInstructor($this->instructor3);
        $lesson->setSeries($series);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // getAllInstructors should return all unique instructors
        $allInstructors = $lesson->getAllInstructors();
        $this->assertCount(3, $allInstructors);
        $this->assertContains($this->instructor1, $allInstructors);
        $this->assertContains($this->instructor2, $allInstructors);
        $this->assertContains($this->instructor3, $allInstructors);
    }

    public function testInstructorCanBeRemovedFromLesson(): void
    {
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            schedule: Clock::get()->now()->modify('+1 day'),
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata);
        $lesson->addInstructor($this->instructor1);
        $lesson->addInstructor($this->instructor2);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $this->assertCount(2, $lesson->getInstructors());

        $lesson->removeInstructor($this->instructor1);
        $this->entityManager->flush();

        $this->assertCount(1, $lesson->getInstructors());
        $this->assertFalse($lesson->getInstructors()->contains($this->instructor1));
        $this->assertTrue($lesson->getInstructors()->contains($this->instructor2));
    }

    public function testDuplicateInstructorsAreRemoved(): void
    {
        // Create series with instructor
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->addInstructor($this->instructor1);
        $this->entityManager->persist($series);

        // Create lesson with same instructor
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            schedule: Clock::get()->now()->modify('+1 day'),
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata);
        $lesson->addInstructor($this->instructor1);
        $lesson->addInstructor($this->instructor2);
        $lesson->setSeries($series);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // getAllInstructors should remove duplicates
        $allInstructors = $lesson->getAllInstructors();
        $this->assertCount(2, $allInstructors);
        $this->assertContains($this->instructor1, $allInstructors);
        $this->assertContains($this->instructor2, $allInstructors);
    }
}
