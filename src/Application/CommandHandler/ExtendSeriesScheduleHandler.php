<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ExtendSeriesSchedule;
use App\Entity\Lesson;
use App\Entity\WorkshopType;
use App\Repository\SeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ExtendSeriesScheduleHandler
{
    public function __construct(
        private SeriesRepository $seriesRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ExtendSeriesSchedule $command): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $horizon = $now->modify('+2 months');

        $activeSeries = $this->seriesRepository->findActive();
        foreach ($activeSeries as $series) {
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

            $cursor = $last->getMetadata()
                ->schedule->modify('+1 week');
            if ($cursor > $horizon) {
                // Already beyond horizon
                continue;
            }

            while ($cursor <= $horizon) {
                // Prevent duplicates if a lesson at this schedule already exists in the series
                $exists = false;
                foreach ($series->lessons as $existing) {
                    if ($existing->getMetadata()->schedule === $cursor) { // compare value
                        $exists = true;
                        break;
                    }
                }

                if (! $exists) {
                    $newMetadata = $last->getMetadata()
                        ->withSchedule($cursor);
                    $newLesson = new Lesson($newMetadata);
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
