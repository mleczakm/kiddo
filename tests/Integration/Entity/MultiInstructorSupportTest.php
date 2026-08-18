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
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
class MultiInstructorSupportTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private User $instructor1;

    private User $instructor2;

    private User $instructor3;

    #[\Override]
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

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testSeriesCanHaveMultipleInstructors(): void
    {
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->addInstructor($this->instructor1);
        $series->addInstructor($this->instructor2);
        $this->entityManager->persist($series);
        $this->entityManager->flush();

        static::assertCount(2, $series->getInstructors());
        static::assertTrue($series->getInstructors()->contains($this->instructor1));
        static::assertTrue($series->getInstructors()->contains($this->instructor2));
    }

    public function testInstructorCanBeRemovedFromSeries(): void
    {
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->addInstructor($this->instructor1);
        $series->addInstructor($this->instructor2);
        $this->entityManager->persist($series);
        $this->entityManager->flush();

        static::assertCount(2, $series->getInstructors());

        $series->removeInstructor($this->instructor1);
        $this->entityManager->flush();

        static::assertCount(1, $series->getInstructors());
        static::assertFalse($series->getInstructors()->contains($this->instructor1));
        static::assertTrue($series->getInstructors()->contains($this->instructor2));
    }

    public function testLessonCanHaveMultipleInstructors(): void
    {
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata, Clock::get()->now()->modify('+1 day'));
        $lesson->addInstructor($this->instructor1);
        $lesson->addInstructor($this->instructor2);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        static::assertCount(2, $lesson->getInstructors());
        static::assertTrue($lesson->getInstructors()->contains($this->instructor1));
        static::assertTrue($lesson->getInstructors()->contains($this->instructor2));
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
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata, Clock::get()->now()->modify('+1 day'));
        $lesson->addInstructor($this->instructor3);
        $lesson->setSeries($series);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // getAllInstructors should return all unique instructors
        $allInstructors = $lesson->getAllInstructors();
        static::assertCount(3, $allInstructors);
        static::assertContains($this->instructor1, $allInstructors);
        static::assertContains($this->instructor2, $allInstructors);
        static::assertContains($this->instructor3, $allInstructors);
    }

    public function testInstructorCanBeRemovedFromLesson(): void
    {
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata, Clock::get()->now()->modify('+1 day'));
        $lesson->addInstructor($this->instructor1);
        $lesson->addInstructor($this->instructor2);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        static::assertCount(2, $lesson->getInstructors());

        $lesson->removeInstructor($this->instructor1);
        $this->entityManager->flush();

        static::assertCount(1, $lesson->getInstructors());
        static::assertFalse($lesson->getInstructors()->contains($this->instructor1));
        static::assertTrue($lesson->getInstructors()->contains($this->instructor2));
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
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $lesson = new Lesson($metadata, Clock::get()->now()->modify('+1 day'));
        $lesson->addInstructor($this->instructor1);
        $lesson->addInstructor($this->instructor2);
        $lesson->setSeries($series);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // getAllInstructors should remove duplicates
        $allInstructors = $lesson->getAllInstructors();
        static::assertCount(2, $allInstructors);
        static::assertContains($this->instructor1, $allInstructors);
        static::assertContains($this->instructor2, $allInstructors);
    }
}
