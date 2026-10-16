<?php

declare(strict_types=1);

namespace App\Application\Accounting;

/**
 * The calendar month the admin dashboard is looking at, addressed as `Y-m`.
 */
final class ReportingMonth
{
    /** First instant of the month named by $key, or of $now's month when $key is empty or malformed. */
    public static function start(?string $key, \DateTimeImmutable $now): \DateTimeImmutable
    {
        if ($key !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $key) === 1) {
            return new \DateTimeImmutable($key . '-01 00:00:00', $now->getTimezone());
        }

        return $now->modify('first day of this month')->setTime(0, 0, 0);
    }

    public static function end(\DateTimeImmutable $start): \DateTimeImmutable
    {
        return $start->modify('last day of this month')->setTime(23, 59, 59);
    }

    public static function isCurrent(\DateTimeImmutable $start, \DateTimeImmutable $now): bool
    {
        return $start->format('Y-m') === $now->format('Y-m');
    }

    public static function label(\DateTimeImmutable $start): string
    {
        $formatter = new \IntlDateFormatter('pl_PL', pattern: 'LLLL');

        return ucfirst((string) $formatter->format($start));
    }
}
