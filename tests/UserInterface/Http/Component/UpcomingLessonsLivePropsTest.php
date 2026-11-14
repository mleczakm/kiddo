<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\UserInterface\Http\Component\UpcomingLessons;
use PHPUnit\Framework\Attributes\Group;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[Group('unit')]
final class UpcomingLessonsLivePropsTest extends UpcomingLessonsTestCase
{
    public function testCanSetShowSearchToFalse(): void
    {
        $this->component->showSearch = false;
        static::assertFalse($this->component->showSearch);
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
}
