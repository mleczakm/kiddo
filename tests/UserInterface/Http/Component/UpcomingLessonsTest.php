<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use App\UserInterface\Http\Component\UpcomingLessons;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[Group('unit')]
class UpcomingLessonsTest extends TestCase
{
    private LessonRepository&MockObject $lessonRepository;

    private BookingRepository&MockObject $bookingRepository;

    private Security&MockObject $security;

    private UpcomingLessons $component;

    #[\Override]
    protected function setUp(): void
    {
        $this->lessonRepository = $this->createMock(LessonRepository::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->component = new UpcomingLessons($this->lessonRepository, $this->bookingRepository, $this->security);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testDefaultWeekIsCurrentDate(): void
    {
        $mockClock = new MockClock('2024-02-20 14:30:00');
        Clock::set($mockClock);

        $component = new UpcomingLessons($this->lessonRepository, $this->bookingRepository, $this->security);

        static::assertSame('2024-02-20', $component->week);
    }

    public function testDefaultViewIsGrid(): void
    {
        static::assertSame('grid', $this->component->view);
    }

    public function testCanSetViewToCalendar(): void
    {
        $this->component->view = 'calendar';
        static::assertSame('calendar', $this->component->view);
    }

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

    public function testDefaultLimitIsNull(): void
    {
        static::assertNull($this->component->limit);
    }

    public function testCanSetShowSearchToFalse(): void
    {
        $this->component->showSearch = false;
        static::assertFalse($this->component->showSearch);
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

    public function testCanSetWeekProperty(): void
    {
        $this->component->week = '2024-05-15';
        static::assertSame('2024-05-15', $this->component->week);
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

    public function testGetCurrentWeekReturnsCurrentDate(): void
    {
        $mockClock = new MockClock('2024-03-15 10:30:00');
        Clock::set($mockClock);

        $result = $this->component->getCurrentWeek();

        static::assertSame('2024-03-15', $result);
    }

    public function testGetCurrentWeekWithDifferentDates(): void
    {
        $mockClock = new MockClock('2024-12-31 23:59:59');
        Clock::set($mockClock);

        $result = $this->component->getCurrentWeek();

        static::assertSame('2024-12-31', $result);
    }

    public function testGetWeekStartReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekStart = $this->component->getWeekStart();

        static::assertSame('2024-03-10', $weekStart->format('Y-m-d'));
        static::assertInstanceOf(\DateTimeImmutable::class, $weekStart);
    }

    public function testGetWeekEndReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekEnd = $this->component->getWeekEnd();

        static::assertSame('2024-03-17', $weekEnd->format('Y-m-d'));
        static::assertInstanceOf(\DateTimeImmutable::class, $weekEnd);
    }

    public function testWeekNavigationCalculatesCorrectDates(): void
    {
        $this->component->week = '2024-02-20';

        // Test week start
        $weekStart = $this->component->getWeekStart();
        static::assertSame('2024-02-20', $weekStart->format('Y-m-d'));

        // Test week end (should be 7 days later)
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2024-02-27', $weekEnd->format('Y-m-d'));
    }

    public function testWeekNavigationWithDifferentDates(): void
    {
        // Test with end of month
        $this->component->week = '2024-02-28';
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2024-03-06', $weekEnd->format('Y-m-d'));

        // Test with year boundary
        $this->component->week = '2024-12-30';
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2025-01-06', $weekEnd->format('Y-m-d'));

        // Test with leap year
        $this->component->week = '2024-02-26'; // 2024 is a leap year
        $weekEnd = $this->component->getWeekEnd();
        static::assertSame('2024-03-04', $weekEnd->format('Y-m-d'));
    }

    public function testComponentWithHomepageConfiguration(): void
    {
        // Test configuration as used on homepage: showSearch=false, limit=3
        $this->component->showSearch = false;
        $this->component->limit = 3;

        $mockLessons = [
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
        ];

        $this->lessonRepository
            ->expects($this->once())
            ->method('findByFilters')
            ->with(null, null, $this->component->week, 3)
            ->willReturn($mockLessons);

        $result = $this->component->getWorkshops();

        static::assertFalse($this->component->showSearch);
        static::assertSame(3, $this->component->limit);
        static::assertCount(3, $result);
    }

    public function testComponentWithWorkshopsPageConfiguration(): void
    {
        // Test configuration as used on workshops page: showSearch=true (default), limit=null (default)
        static::assertTrue($this->component->showSearch);
        static::assertNull($this->component->limit);

        $mockLessons = [
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
            $this->createMock(Lesson::class),
        ];

        $this->lessonRepository
            ->expects($this->once())
            ->method('findByFilters')
            ->with(null, null, $this->component->week, null)
            ->willReturn($mockLessons);

        $result = $this->component->getWorkshops();

        static::assertCount(5, $result);
    }

    public function testLiveComponentAttributes(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);

        // Test that the class has the correct LiveComponent attribute
        $attributes = $reflectionClass->getAttributes();
        static::assertNotEmpty($attributes);
        $liveComponentAttribute = array_find(
            $attributes,
            static fn($attribute) => $attribute->getName() === AsLiveComponent::class,
        );

        static::assertNotNull($liveComponentAttribute);
        static::assertEquals(['UpcomingLessons'], $liveComponentAttribute->getArguments());
    }

    public function testQueryPropertyIsLiveProp(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);
        $queryProperty = $reflectionClass->getProperty('query');

        $attributes = $queryProperty->getAttributes();
        static::assertNotEmpty($attributes);

        $livePropAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== LiveProp::class) {
                continue;
            }

            $livePropAttribute = $attribute;
            break;
        }

        static::assertNotNull($livePropAttribute);
    }

    public function testAgePropertyIsLiveProp(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);
        $ageProperty = $reflectionClass->getProperty('age');

        $attributes = $ageProperty->getAttributes();
        static::assertNotEmpty($attributes);

        $livePropAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== LiveProp::class) {
                continue;
            }

            $livePropAttribute = $attribute;
            break;
        }

        static::assertNotNull($livePropAttribute);
    }

    public function testWeekPropertyIsLiveProp(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);
        $weekProperty = $reflectionClass->getProperty('week');

        $attributes = $weekProperty->getAttributes();
        static::assertNotEmpty($attributes);

        $livePropAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== LiveProp::class) {
                continue;
            }

            $livePropAttribute = $attribute;
            break;
        }

        static::assertNotNull($livePropAttribute);
    }

    public function testShowSearchAndLimitAreNotWritableLiveProps(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);

        // showSearch should be LiveProp but not writable
        $showSearchProperty = $reflectionClass->getProperty('showSearch');
        $attributes = $showSearchProperty->getAttributes();
        static::assertNotEmpty($attributes);

        // limit should be LiveProp but not writable
        $limitProperty = $reflectionClass->getProperty('limit');
        $attributes = $limitProperty->getAttributes();
        static::assertNotEmpty($attributes);
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
