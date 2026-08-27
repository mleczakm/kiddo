<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\HolidayChecker;
use App\Infrastructure\Calendar\PolishPublicHolidays;
use Mleczakm\AeonSchoolHolidays\PolishSchoolHolidays;
use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HolidayCheckerTest extends TestCase
{
    private HolidayChecker $checker;

    #[\Override]
    protected function setUp(): void
    {
        $this->checker = new HolidayChecker([
            new PolishPublicHolidays(),
            new PolishSchoolHolidays(Voivodeship::Masovian),
        ]);
    }

    #[Test]
    public function returnsNameForSchoolHoliday(): void
    {
        self::assertSame(['Ferie zimowe'], $this->checker->holidayNamesAt(new \DateTimeImmutable('2027-02-01')));
    }

    #[Test]
    public function returnsNameForPolishPublicHoliday(): void
    {
        self::assertSame(
            ['Narodowe Święto Niepodległości'],
            $this->checker->holidayNamesAt(new \DateTimeImmutable('2026-11-11')),
        );
    }

    #[Test]
    public function returnsAllNamesWhenPublicAndSchoolHolidaysOverlap(): void
    {
        self::assertSame(
            ['pierwszy dzień Bożego Narodzenia', 'Zimowa przerwa świąteczna'],
            $this->checker->holidayNamesAt(new \DateTimeImmutable('2026-12-25')),
        );
    }

    #[Test]
    public function returnsEmptyListOutsideHolidays(): void
    {
        self::assertSame([], $this->checker->holidayNamesAt(new \DateTimeImmutable('2027-03-01')));
    }

    #[Test]
    public function stillReturnsPublicHolidayWhenSchoolHolidayDataDoesNotCoverTheYear(): void
    {
        self::assertSame(
            ['Narodowe Święto Niepodległości'],
            $this->checker->holidayNamesAt(new \DateTimeImmutable('2030-11-11')),
        );
    }
}
