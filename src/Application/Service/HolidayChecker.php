<?php

declare(strict_types=1);

namespace App\Application\Service;

use Aeon\Calendar\Exception\HolidayYearException;
use Aeon\Calendar\Gregorian\Day;
use Aeon\Calendar\Holidays;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class HolidayChecker
{
    /**
     * @param iterable<Holidays> $holidayProviders
     */
    public function __construct(
        private iterable $holidayProviders,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /** @return list<string> */
    public function holidayNamesAt(\DateTimeInterface $date, string $locale = 'pl'): array
    {
        $day = Day::fromDateTime($date);
        $names = [];

        foreach ($this->holidayProviders as $provider) {
            try {
                foreach ($provider->holidaysAt($day) as $holiday) {
                    $name = $holiday->name($locale);
                    $names[$name] = $name;
                }
            } catch (HolidayYearException $exception) {
                $this->logger->warning('Holiday data is unavailable for the lesson date.', [
                    'date' => $date->format('Y-m-d'),
                    'provider' => $provider::class,
                    'exception' => $exception,
                ]);
            }
        }

        return array_values($names);
    }
}
