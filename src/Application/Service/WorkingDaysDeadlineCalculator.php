<?php

declare(strict_types=1);

namespace App\Application\Service;

use Aeon\Calendar\BusinessHours\BusinessDays;
use Aeon\Calendar\BusinessHours\WorkingHours\LinearWorkingHours;
use Aeon\Calendar\Gregorian\DateTime as AeonDateTime;
use Aeon\Calendar\Gregorian\Time;
use Aeon\Calendar\Gregorian\TimeZone;
use Aeon\Calendar\TimeUnit;

/**
 * Adds a duration to a starting point counting only working days (Monday-Friday).
 * Time that falls within a weekend does not count toward the elapsed duration -
 * e.g. adding 24 hours to a Friday evening lands on the following Monday evening,
 * because a bank transfer sent over the weekend can't settle until Monday.
 */
final class WorkingDaysDeadlineCalculator
{
    private readonly BusinessDays $workingDays;

    public function __construct()
    {
        $this->workingDays = BusinessDays::mondayFriday(
            new LinearWorkingHours(new Time(0, 0, 0), new Time(23, 59, 59)),
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
            if (!$this->workingDays->isOpenOn($cursor->day())) {
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
