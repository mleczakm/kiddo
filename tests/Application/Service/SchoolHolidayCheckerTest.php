<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\SchoolHolidayChecker;
use Mleczakm\AeonSchoolHolidays\PolishSchoolHolidays;
use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SchoolHolidayCheckerTest extends TestCase
{
    private SchoolHolidayChecker $checker;

    #[\Override]
    protected function setUp(): void
    {
        $this->checker = new SchoolHolidayChecker(new PolishSchoolHolidays(Voivodeship::Masovian));
    }

    #[Test]
    public function returnsPolishNameForSchoolHoliday(): void
    {
        self::assertSame('Ferie zimowe', $this->checker->holidayNameAt(new \DateTimeImmutable('2027-02-01')));
    }

    #[Test]
    public function returnsNullOutsideSchoolHolidays(): void
    {
        self::assertNull($this->checker->holidayNameAt(new \DateTimeImmutable('2027-03-01')));
    }

    #[Test]
    public function returnsNullWhenHolidayDataDoesNotCoverTheSchoolYear(): void
    {
        self::assertNull($this->checker->holidayNameAt(new \DateTimeImmutable('2030-02-01')));
    }
}
