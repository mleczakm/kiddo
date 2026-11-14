<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class UpcomingLessonsModalTest extends UpcomingLessonsTestCase
{
    public function testDefaultViewIsGrid(): void
    {
        static::assertSame('grid', $this->component->view);
    }

    public function testCanSetViewToCalendar(): void
    {
        $this->component->view = 'calendar';
        static::assertSame('calendar', $this->component->view);
    }

    public function testDefaultLimitIsNull(): void
    {
        static::assertNull($this->component->limit);
    }

    public function testShouldOpenModalMatchesSlugDateAndHour(): void
    {
        $metadata = new LessonMetadata(
            title: 'Bałaganki',
            lead: 'Lead',
            visualTheme: '#fff',
            description: 'Desc',
            capacity: 10,
            duration: 60,
            ageRange: new AgeRange(0, 3),
            category: 'test',
            slug: 'balaganki',
        );
        $lesson = $this->createMock(Lesson::class);
        $lesson->method('getMetadata')->willReturn($metadata);
        $lesson->schedule = new \DateTimeImmutable('2024-02-21 10:30:00');

        $this->component->openSlug = 'balaganki';
        $this->component->openDate = '2024-02-21';
        $this->component->openHour = '10:30';

        static::assertTrue($this->component->shouldOpenModal($lesson));

        $this->component->openHour = '11:00';
        static::assertFalse($this->component->shouldOpenModal($lesson));
    }

    public function testSetViewResetsModalProperties(): void
    {
        // Set up modal properties
        $this->component->openSlug = 'balaganki';
        $this->component->openDate = '2024-02-21';
        $this->component->openHour = '10:30';
        $this->component->view = 'grid';

        // Call setView
        $this->component->setView('calendar');

        // Verify view changed
        static::assertSame('calendar', $this->component->view);

        // Verify modal properties reset
        static::assertNull($this->component->openSlug);
        static::assertNull($this->component->openDate);
        static::assertNull($this->component->openHour);
    }

    public function testSetViewDoesNotAffectOtherProperties(): void
    {
        $this->component->query = 'test query';
        $this->component->age = 5;
        $this->component->week = '2024-02-20';
        $this->component->limit = 3;
        $this->component->showSearch = false;

        $this->component->setView('calendar');

        // These should remain unchanged
        static::assertSame('test query', $this->component->query);
        static::assertSame(5, $this->component->age);
        static::assertSame('2024-02-20', $this->component->week);
        static::assertSame(3, $this->component->limit);
        static::assertFalse($this->component->showSearch);
    }
}
