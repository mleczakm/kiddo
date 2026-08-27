<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar;

use Aeon\Calendar\Gregorian\Day;
use Aeon\Calendar\Gregorian\TimePeriod;
use Aeon\Calendar\Holidays;
use Aeon\Calendar\Holidays\Holiday;
use Aeon\Calendar\Holidays\HolidayLocaleName;
use Aeon\Calendar\Holidays\HolidayName;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yasumi\Holiday as YasumiHoliday;
use Yasumi\Provider\Poland;
use Yasumi\Yasumi;

/** Adapts Yasumi's Polish official-holiday calendar to Aeon's Holidays contract. */
final class PolishPublicHolidays implements Holidays
{
    /** @var array<int, array<string, Holiday>> */
    private array $calendars = [];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    #[\Override]
    public function isHoliday(Day $day): bool
    {
        return $this->holidaysAt($day) !== [];
    }

    /** @return list<Holiday> */
    #[\Override]
    public function holidaysAt(Day $day): array
    {
        $holiday = $this->calendarFor($day->year()->number())[$day->toString()] ?? null;

        return $holiday === null ? [] : [$holiday];
    }

    /** @return list<Holiday> */
    #[\Override]
    public function in(TimePeriod $period): array
    {
        $start = $period->start()->day();
        $end = $period->end()->day();
        if ($start->isAfter($end)) {
            return [];
        }

        $holidays = [];
        for ($year = $start->year()->number(); $year <= $end->year()->number(); ++$year) {
            foreach ($this->calendarFor($year) as $holiday) {
                if (!($holiday->day()->isAfterOrEqualTo($start) && $holiday->day()->isBeforeOrEqualTo($end))) {
                    continue;
                }

                $holidays[$holiday->day()->toString()] = $holiday;
            }
        }

        ksort($holidays);

        return array_values($holidays);
    }

    /** @return array<string, Holiday> */
    private function calendarFor(int $year): array
    {
        if (array_key_exists($year, $this->calendars)) {
            return $this->calendars[$year];
        }

        $calendar = [];
        try {
            $provider = Yasumi::create(Poland::class, $year, 'pl_PL');
            foreach ($provider->getHolidays() as $yasumiHoliday) {
                if ($yasumiHoliday->getType() !== YasumiHoliday::TYPE_OFFICIAL) {
                    continue;
                }

                $day = Day::fromDateTime($yasumiHoliday);
                $calendar[$day->toString()] = new Holiday(
                    $day,
                    new HolidayName(
                        new HolidayLocaleName('pl', $yasumiHoliday->getName(['pl_PL', 'pl'])),
                        new HolidayLocaleName('en', $yasumiHoliday->getName(['en_US', 'en'])),
                    ),
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to load Yasumi Polish public holidays.', [
                'year' => $year,
                'exception' => $exception,
            ]);

            return $this->calendars[$year] = [];
        }
        ksort($calendar);

        return $this->calendars[$year] = $calendar;
    }
}
