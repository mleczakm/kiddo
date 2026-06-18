<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\UserInterface\Http\Component\AdminKpiStatsComponent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class AdminKpiStatsComponentTest extends TestCase
{
    public function testCanRender(): void
    {
        $component = new AdminKpiStatsComponent();

        $kpiStats = $component->getKpiStats();
        $this->assertArrayHasKey('bookingsCount', $kpiStats);
        $this->assertArrayHasKey('revenue', $kpiStats);
        $this->assertArrayHasKey('occupancyRate', $kpiStats);
    }

    public function testKpiStatsReturnsCorrectStructure(): void
    {
        $component = new AdminKpiStatsComponent();

        $kpiStats = $component->getKpiStats();

        $this->assertIsInt($kpiStats['bookingsCount']);
        $this->assertIsString($kpiStats['revenue']);
        $this->assertIsString($kpiStats['occupancyRate']);
    }

    public function testKpiStatsReturnsExpectedValues(): void
    {
        $component = new AdminKpiStatsComponent();

        $kpiStats = $component->getKpiStats();

        $this->assertEquals(142, $kpiStats['bookingsCount']);
        $this->assertEquals('7 850 zł', $kpiStats['revenue']);
        $this->assertEquals('78%', $kpiStats['occupancyRate']);
    }
}
