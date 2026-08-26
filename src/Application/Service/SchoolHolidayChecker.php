<?php

declare(strict_types=1);

namespace App\Application\Service;

use Aeon\Calendar\Exception\HolidayYearException;
use Aeon\Calendar\Gregorian\Day;
use Aeon\Calendar\Holidays;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class SchoolHolidayChecker
{
    public function __construct(
        private Holidays $holidays,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function holidayNameAt(\DateTimeInterface $date, string $locale = 'pl'): ?string
    {
        try {
            $holiday = $this->holidays->holidaysAt(Day::fromDateTime($date))[0] ?? null;
        } catch (HolidayYearException $exception) {
            $this->logger->warning('School holiday data is unavailable for the lesson date.', [
                'date' => $date->format('Y-m-d'),
                'exception' => $exception,
            ]);

            return null;
        }

        return $holiday?->name($locale);
    }
}
