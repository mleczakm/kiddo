<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\UserInterface\Http\Component\AdminActivityLogComponent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class AdminActivityLogComponentTest extends TestCase
{
    public function testCanRender(): void
    {
        $component = new AdminActivityLogComponent();

        $activityLog = $component->getActivityLog();
        $this->assertNotEmpty($activityLog);
    }

    public function testActivityLogReturnsCorrectStructure(): void
    {
        $component = new AdminActivityLogComponent();

        $activityLog = $component->getActivityLog();

        $this->assertArrayHasKey('type', $activityLog[0]);
        $this->assertArrayHasKey('badge', $activityLog[0]);
        $this->assertArrayHasKey('name', $activityLog[0]);
        $this->assertArrayHasKey('lesson', $activityLog[0]);
        $this->assertArrayHasKey('notes', $activityLog[0]);
    }

    public function testActivityLogReturnsExpectedData(): void
    {
        $component = new AdminActivityLogComponent();

        $activityLog = $component->getActivityLog();

        $this->assertCount(2, $activityLog);
        $this->assertEquals('cancelled', $activityLog[0]['type']);
        $this->assertEquals('reserved', $activityLog[1]['type']);
        $this->assertEquals('Odwołana obecność', $activityLog[0]['badge']);
        $this->assertEquals('Zapis na rezerwową', $activityLog[1]['badge']);
    }
}
