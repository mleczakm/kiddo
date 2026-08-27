<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Calendar;

use Aeon\Calendar\Gregorian\Day;
use App\Infrastructure\Calendar\PolishPublicHolidays;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PolishPublicHolidaysTest extends TestCase
{
    private PolishPublicHolidays $holidays;

    #[\Override]
    protected function setUp(): void
    {
        $this->holidays = new PolishPublicHolidays();
    }

    #[Test]
    public function detectsFixedPublicHoliday(): void
    {
        $holiday = $this->holidays->holidaysAt(Day::fromString('2026-11-11'))[0] ?? null;

        self::assertNotNull($holiday);
        self::assertSame('Narodowe Święto Niepodległości', $holiday->name('pl'));
    }

    #[Test]
    public function detectsMovablePublicHolidays(): void
    {
        self::assertTrue($this->holidays->isHoliday(Day::fromString('2026-04-06')));
        self::assertTrue($this->holidays->isHoliday(Day::fromString('2026-06-04')));
    }

    #[Test]
    public function includesChristmasEveFrom2025(): void
    {
        self::assertFalse($this->holidays->isHoliday(Day::fromString('2024-12-24')));
        self::assertTrue($this->holidays->isHoliday(Day::fromString('2025-12-24')));
    }

    #[Test]
    public function doesNotTreatOrdinarySundayAsNamedPublicHoliday(): void
    {
        self::assertFalse($this->holidays->isHoliday(Day::fromString('2026-11-08')));
    }
}
