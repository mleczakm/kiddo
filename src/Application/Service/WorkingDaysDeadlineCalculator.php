<?php

declare(strict_types=1);

namespace App\Application\Service;

use Aeon\Calendar\BusinessHours\BusinessDays;
use Aeon\Calendar\BusinessHours\BusinessHours;
use Aeon\Calendar\BusinessHours\NonBusinessDay\Holidays as HolidayNonBusinessDay;
use Aeon\Calendar\BusinessHours\NonBusinessDays;
use Aeon\Calendar\BusinessHours\WorkingHours\LinearWorkingHours;
use Aeon\Calendar\Gregorian\DateTime as AeonDateTime;
use Aeon\Calendar\Gregorian\Time;
use Aeon\Calendar\Gregorian\TimeZone;
use Aeon\Calendar\Holidays;
use Aeon\Calendar\TimeUnit;
use App\Infrastructure\Calendar\PolishPublicHolidays;

/**
 * Adds a duration to a starting point counting only working days: Monday-Friday
 * that are not Polish public holidays. Time that falls on a weekend or a public
 * holiday does not count toward the elapsed duration - e.g. adding 24 hours to a
 * Friday evening lands on the following Monday evening (or later, if that Monday
 * is a holiday), because a bank transfer sent then can't settle until the next
 * business day.
 */
final class WorkingDaysDeadlineCalculator
{
    private readonly BusinessHours $businessHours;

    public function __construct(Holidays $publicHolidays = new PolishPublicHolidays())
    {
        $this->businessHours = new BusinessHours(
            BusinessDays::mondayFriday(new LinearWorkingHours(new Time(0, 0, 0), new Time(23, 59, 59))),
            BusinessDays::none(),
            new NonBusinessDays(new HolidayNonBusinessDay($publicHolidays)),
        );
    }

    /**
     * @throws \Aeon\Calendar\Exception\InvalidArgumentException
     */
    public function addWorkingTime(\DateTimeImmutable $from, TimeUnit $duration): \DateTimeImmutable
    {
        $timeZone = TimeZone::UTC();
        $cursor = AeonDateTime::fromDateTime($from);
        $remaining = $duration;

        while (!$remaining->isZero()) {
            if (!$this->businessHours->isOpenOn($cursor->day())) {
                $cursor = $cursor->day()->next()->midnight($timeZone);
                continue;
            }

            $nextMidnight = $cursor->day()->next()->midnight($timeZone);
            $availableToday = $cursor->distance($nextMidnight);

            if ($availableToday->isGreaterThanOrEqualTo($remaining)) {
                return $cursor->add($remaining)->toDateTimeImmutable();
            }

            $remaining = $remaining->sub($availableToday);
            $cursor = $nextMidnight;
        }

        return $cursor->toDateTimeImmutable();
    }
}
