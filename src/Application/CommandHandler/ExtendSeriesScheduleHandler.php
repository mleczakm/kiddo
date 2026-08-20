<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ExtendSeriesSchedule;
use App\Application\Repository\SeriesRepositoryInterface;
use App\Entity\Lesson;
use App\Entity\WorkshopType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ExtendSeriesScheduleHandler
{
    public function __construct(
        private SeriesRepositoryInterface $seriesRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ExtendSeriesSchedule $_command): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $horizon = $now->modify('+2 months');

        $activeSeries = $this->seriesRepository->findActive();
        foreach ($activeSeries as $series) {
            if ($series->lastOccurrenceDate !== null && $now >= $series->lastOccurrenceDate->modify('+1 week')) {
                $series->status = 'cancelled';
                continue;
            }

            // Only extend WEEKLY series for now
            if ($series->type !== WorkshopType::WEEKLY) {
                continue;
            }

            // Skip if series has no lessons yet
            try {
                $last = $series->getLastLesson();
            } catch (\LogicException) {
                $this->logger->warning('Skipping series without lessons when extending schedule', [
                    'seriesId' => (string) $series->getId(),
                ]);
                continue;
            }

            $cursor = $last->schedule->modify('+1 week');
            $seriesHorizon = $series->lastOccurrenceDate !== null
                ? min($horizon, $series->lastOccurrenceDate)
                : $horizon;
            if ($cursor > $seriesHorizon) {
                // Already beyond horizon (or past the series' last occurrence date)
                continue;
            }

            while ($cursor <= $seriesHorizon) {
                // Prevent duplicates if a lesson at this schedule already exists in the series
                $exists = false;
                foreach ($series->lessons as $existing) {
                    if ($existing->schedule->getTimestamp() !== $cursor->getTimestamp()) {
                        continue;
                    }

                    $exists = true;
                    break;
                }

                if (!$exists) {
                    // Reuses the same LessonMetadata row as $last (rather than
                    // cloning a new one per occurrence) since content is
                    // identical across a weekly series' generated occurrences
                    // — only the schedule differs.
                    $newLesson = new Lesson($last->getMetadata(), $cursor);
                    $newLesson->setSeries($series);
                    // Ticket options: rely on series-level options + lesson-level already in constructor
                    $this->em->persist($newLesson);
                }

                $cursor = $cursor->modify('+1 week');
            }
        }

        $this->em->flush();
    }
}
