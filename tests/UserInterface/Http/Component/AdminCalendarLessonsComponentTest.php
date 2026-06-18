<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\UserInterface\Http\Component\AdminCalendarLessonsComponent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class AdminCalendarLessonsComponentTest extends TestCase
{
    public function testCanRender(): void
    {
        $component = new AdminCalendarLessonsComponent();

        $calendarLessons = $component->getCalendarLessons();
        $this->assertNotEmpty($calendarLessons);
    }

    public function testCalendarLessonsReturnsCorrectStructure(): void
    {
        $component = new AdminCalendarLessonsComponent();

        $calendarLessons = $component->getCalendarLessons();

        $this->assertArrayHasKey('time', $calendarLessons[0]);
        $this->assertArrayHasKey('title', $calendarLessons[0]);
        $this->assertArrayHasKey('instructor', $calendarLessons[0]);
        $this->assertArrayHasKey('age', $calendarLessons[0]);
        $this->assertArrayHasKey('capacity', $calendarLessons[0]);
        $this->assertArrayHasKey('status', $calendarLessons[0]);
    }

    public function testCalendarLessonsReturnsExpectedData(): void
    {
        $component = new AdminCalendarLessonsComponent();

        $calendarLessons = $component->getCalendarLessons();

        $this->assertCount(1, $calendarLessons);
        $this->assertEquals('10:00 - 11:30', $calendarLessons[0]['time']);
        $this->assertEquals('Sensoplastyka dla maluchów', $calendarLessons[0]['title']);
        $this->assertEquals('Marta W.', $calendarLessons[0]['instructor']);
        $this->assertEquals('0-3 lat', $calendarLessons[0]['age']);
        $this->assertEquals('8/10', $calendarLessons[0]['capacity']);
        $this->assertEquals('Odbędzie się', $calendarLessons[0]['status']);
    }
}
