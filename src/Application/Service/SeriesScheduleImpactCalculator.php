<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Series;
use App\Entity\WorkshopType;

final readonly class SeriesScheduleImpactCalculator
{
    public function __construct(
        private SeriesSchedulePlanner $planner,
    ) {}

    /** @throws \InvalidArgumentException */
    public function forNew(WorkshopType $type, \DateTimeImmutable $start, \DateTimeImmutable $end): SeriesScheduleImpact
    {
        return new SeriesScheduleImpact(create: count($this->planner->plan($type, $start, $end)));
    }

    /** @throws \InvalidArgumentException */
    public function forExisting(Series $series, ?\DateTimeImmutable $end): SeriesScheduleImpact
    {
        if ($end === null || $series->type !== WorkshopType::WEEKLY || $series->lessons->isEmpty()) {
            return new SeriesScheduleImpact();
        }

        $endOfDay = $end->setTime(23, 59, 59);
        $existingTimestamps = [];
        $hide = 0;
        $delete = 0;
        foreach ($series->lessons as $lesson) {
            $existingTimestamps[$lesson->schedule->getTimestamp()] = true;
            if ($lesson->schedule <= $endOfDay) {
                continue;
            }

            if ($lesson->getBookings()->isEmpty()) {
                $delete++;
                continue;
            }

            $hide += (int) $lesson->visible;
        }

        $create = 0;
        foreach ($this->planner->plan($series->type, $series->getFirstLesson()->schedule, $end) as $schedule) {
            if (array_key_exists($schedule->getTimestamp(), $existingTimestamps)) {
                continue;
            }

            $create++;
        }

        return new SeriesScheduleImpact($create, $hide, $delete);
    }
}
