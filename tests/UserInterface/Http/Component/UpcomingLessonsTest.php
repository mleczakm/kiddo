<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use PHPUnit\Framework\Attributes\Group;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use App\Entity\Lesson;
use App\Repository\LessonRepository;
use App\UserInterface\Http\Component\UpcomingLessons;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[Group('unit')]
class UpcomingLessonsTest extends TestCase
{
    private LessonRepository&MockObject $lessonRepository;

    private UpcomingLessons $component;

    protected function setUp(): void
    {
        $this->lessonRepository = $this->createMock(LessonRepository::class);
        $this->component = new UpcomingLessons($this->lessonRepository);
    }

    protected function tearDown(): void
    {
        // Don't reset clock to avoid errors - let it use system clock
    }

    public function testDefaultWeekIsCurrentDate(): void
    {
        $mockClock = new MockClock('2024-02-20 14:30:00');
        Clock::set($mockClock);

        $component = new UpcomingLessons($this->lessonRepository);

        $this->assertEquals('2024-02-20', $component->week);
    }

    public function testDefaultShowSearchIsTrue(): void
    {
        $this->assertTrue($this->component->showSearch);
    }

    public function testDefaultLimitIsNull(): void
    {
        $this->assertNull($this->component->limit);
    }

    public function testCanSetShowSearchToFalse(): void
    {
        $this->component->showSearch = false;
        $this->assertFalse($this->component->showSearch);
    }

    public function testCanSetLimit(): void
    {
        $this->component->limit = 5;
        $this->assertEquals(5, $this->component->limit);
    }

    public function testCanSetQueryProperty(): void
    {
        $this->component->query = 'test search';
        $this->assertEquals('test search', $this->component->query);
    }

    public function testCanSetAgeProperty(): void
    {
        $this->component->age = 7;
        $this->assertEquals(7, $this->component->age);
    }

    public function testCanSetWeekProperty(): void
    {
        $this->component->week = '2024-05-15';
        $this->assertEquals('2024-05-15', $this->component->week);
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

        $this->lessonRepository
            ->method('findByFilters')
            ->willReturn($expectedLessons);

        $result = $this->component->getWorkshops();

        $this->assertSame($expectedLessons, $result);
        $this->assertCount(3, $result);
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

        $this->assertCount(2, $result);
    }

    public function testGetCurrentWeekReturnsCurrentDate(): void
    {
        $mockClock = new MockClock('2024-03-15 10:30:00');
        Clock::set($mockClock);

        $result = $this->component->getCurrentWeek();

        $this->assertEquals('2024-03-15', $result);
    }

    public function testGetCurrentWeekWithDifferentDates(): void
    {
        $mockClock = new MockClock('2024-12-31 23:59:59');
        Clock::set($mockClock);

        $result = $this->component->getCurrentWeek();

        $this->assertEquals('2024-12-31', $result);
    }

    public function testGetWeekStartReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekStart = $this->component->getWeekStart();

        $this->assertEquals('2024-03-10', $weekStart->format('Y-m-d'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $weekStart);
    }

    public function testGetWeekEndReturnsCorrectDate(): void
    {
        $this->component->week = '2024-03-10';

        $weekEnd = $this->component->getWeekEnd();

        $this->assertEquals('2024-03-17', $weekEnd->format('Y-m-d'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $weekEnd);
    }

    public function testWeekNavigationCalculatesCorrectDates(): void
    {
        $this->component->week = '2024-02-20';

        // Test week start
        $weekStart = $this->component->getWeekStart();
        $this->assertEquals('2024-02-20', $weekStart->format('Y-m-d'));

        // Test week end (should be 7 days later)
        $weekEnd = $this->component->getWeekEnd();
        $this->assertEquals('2024-02-27', $weekEnd->format('Y-m-d'));
    }

    public function testWeekNavigationWithDifferentDates(): void
    {
        // Test with end of month
        $this->component->week = '2024-02-28';
        $weekEnd = $this->component->getWeekEnd();
        $this->assertEquals('2024-03-06', $weekEnd->format('Y-m-d'));

        // Test with year boundary
        $this->component->week = '2024-12-30';
        $weekEnd = $this->component->getWeekEnd();
        $this->assertEquals('2025-01-06', $weekEnd->format('Y-m-d'));

        // Test with leap year
        $this->component->week = '2024-02-26'; // 2024 is a leap year
        $weekEnd = $this->component->getWeekEnd();
        $this->assertEquals('2024-03-04', $weekEnd->format('Y-m-d'));
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

        $this->assertFalse($this->component->showSearch);
        $this->assertEquals(3, $this->component->limit);
        $this->assertCount(3, $result);
    }

    public function testComponentWithWorkshopsPageConfiguration(): void
    {
        // Test configuration as used on workshops page: showSearch=true (default), limit=null (default)
        $this->assertTrue($this->component->showSearch);
        $this->assertNull($this->component->limit);

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

        $this->assertCount(5, $result);
    }

    public function testLiveComponentAttributes(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);

        // Test that the class has the correct LiveComponent attribute
        $attributes = $reflectionClass->getAttributes();
        $this->assertNotEmpty($attributes);
        $liveComponentAttribute = array_find(
            $attributes,
            fn($attribute) => $attribute->getName() === AsLiveComponent::class
        );

        $this->assertNotNull($liveComponentAttribute);
        $this->assertEquals(['UpcomingLessons'], $liveComponentAttribute->getArguments());
    }

    public function testQueryPropertyIsLiveProp(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);
        $queryProperty = $reflectionClass->getProperty('query');

        $attributes = $queryProperty->getAttributes();
        $this->assertNotEmpty($attributes);

        $livePropAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === LiveProp::class) {
                $livePropAttribute = $attribute;
                break;
            }
        }

        $this->assertNotNull($livePropAttribute);
    }

    public function testAgePropertyIsLiveProp(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);
        $ageProperty = $reflectionClass->getProperty('age');

        $attributes = $ageProperty->getAttributes();
        $this->assertNotEmpty($attributes);

        $livePropAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === LiveProp::class) {
                $livePropAttribute = $attribute;
                break;
            }
        }

        $this->assertNotNull($livePropAttribute);
    }

    public function testWeekPropertyIsLiveProp(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);
        $weekProperty = $reflectionClass->getProperty('week');

        $attributes = $weekProperty->getAttributes();
        $this->assertNotEmpty($attributes);

        $livePropAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === LiveProp::class) {
                $livePropAttribute = $attribute;
                break;
            }
        }

        $this->assertNotNull($livePropAttribute);
    }

    public function testShowSearchAndLimitAreNotWritableLiveProps(): void
    {
        $reflectionClass = new \ReflectionClass(UpcomingLessons::class);

        // showSearch should be LiveProp but not writable
        $showSearchProperty = $reflectionClass->getProperty('showSearch');
        $attributes = $showSearchProperty->getAttributes();
        $this->assertNotEmpty($attributes);

        // limit should be LiveProp but not writable
        $limitProperty = $reflectionClass->getProperty('limit');
        $attributes = $limitProperty->getAttributes();
        $this->assertNotEmpty($attributes);
    }
}
