<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Entity\WorkshopType;
use Doctrine\ORM\EntityManagerInterface;

/** Keeps every finite workshop occurrence materialized as soon as an admin saves. */
final readonly class SeriesScheduleSynchronizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SeriesSchedulePlanner $planner,
        private SeriesScheduleImpactCalculator $impactCalculator,
    ) {}

    /** @throws \InvalidArgumentException */
    public function previewNew(
        WorkshopType $type,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): SeriesScheduleImpact {
        return $this->impactCalculator->forNew($type, $start, $end);
    }

    /**
     * @return list<Lesson>
     * @throws \InvalidArgumentException
     */
    public function createInitialLessons(
        Series $series,
        LessonMetadata $metadata,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        $lessons = [];
        foreach ($this->planner->plan($series->type, $start, $end) as $schedule) {
            $lesson = new Lesson($metadata, $schedule);
            $lesson->setSeries($series);
            $this->entityManager->persist($lesson);
            $lessons[] = $lesson;
        }

        return $lessons;
    }

    /** @throws \InvalidArgumentException */
    public function previewExisting(Series $series, ?\DateTimeImmutable $end): SeriesScheduleImpact
    {
        return $this->impactCalculator->forExisting($series, $end);
    }

    /** @throws \InvalidArgumentException */
    public function synchronize(Series $series, ?\DateTimeImmutable $end): SeriesScheduleImpact
    {
        $impact = $this->previewExisting($series, $end);
        $series->lastOccurrenceDate = $end;

        if ($end === null || $series->type !== WorkshopType::WEEKLY || $series->lessons->isEmpty()) {
            return $impact;
        }

        $endOfDay = $end->setTime(23, 59, 59);
        $existingTimestamps = [];
        foreach ($series->lessons->toArray() as $lesson) {
            $existingTimestamps[$lesson->schedule->getTimestamp()] = true;
            if ($lesson->schedule <= $endOfDay) {
                continue;
            }

            if (!$lesson->getBookings()->isEmpty()) {
                $lesson->visible = false;
                continue;
            }

            $series->lessons->removeElement($lesson);
            $this->entityManager->remove($lesson);
        }

        $metadata = $series->getLastLesson()->getMetadata();
        foreach ($this->planner->plan($series->type, $series->getFirstLesson()->schedule, $end) as $schedule) {
            if (array_key_exists($schedule->getTimestamp(), $existingTimestamps)) {
                continue;
            }

            $lesson = new Lesson($metadata, $schedule);
            $lesson->setSeries($series);
            $this->entityManager->persist($lesson);
        }

        return $impact;
    }
}
