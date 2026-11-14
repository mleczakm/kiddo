<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\UserInterface\Http\Component\UpcomingLessonsComponent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\NativeClock;

abstract class UpcomingLessonsComponentTestCase extends TestCase
{
    protected LessonRepository&MockObject $lessonRepository;

    protected UpcomingLessonsComponent $component;

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

    protected function createMockLesson(string $title): Lesson
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
