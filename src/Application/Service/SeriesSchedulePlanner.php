<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\WorkshopType;

final readonly class SeriesSchedulePlanner
{
    /**
     * @return list<\DateTimeImmutable>
     * @throws \InvalidArgumentException
     */
    public function plan(WorkshopType $type, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $endOfDay = $end->setTime(23, 59, 59);
        if ($start > $endOfDay) {
            throw new \InvalidArgumentException('Data zakończenia cyklu nie może być wcześniejsza niż jego początek.');
        }

        if ($type === WorkshopType::ONE_TIME) {
            return [$start];
        }

        $schedules = [];
        for ($cursor = $start; $cursor <= $endOfDay; $cursor = $cursor->modify('+1 week')) {
            $schedules[] = $cursor;
        }

        return $schedules;
    }
}
