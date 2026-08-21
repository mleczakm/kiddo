<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\BookingRepositoryInterface;
use App\Entity\BookingOccurrence;
use App\Entity\DTO\RescheduledLesson;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BookingOccurrenceBackfill
{
    public function __construct(
        private BookingRepositoryInterface $bookings,
        private EntityManagerInterface $em,
    ) {}

    /** @return array{bookings: int, created: int, mismatches: int} */
    public function run(): array
    {
        $processed = 0;
        $created = 0;
        $mismatches = 0;
        foreach ($this->bookings->findAll() as $booking) {
            ++$processed;
            foreach ($booking->getLessons() as $lesson) {
                if ($booking->findOccurrence($lesson->getId()) !== null) {
                    continue;
                }

                $map = $booking->getLessonsMap();
                $status = match (true) {
                    $map->isRescheduledLesson($lesson->getId()) => BookingOccurrence::STATUS_RESCHEDULED,
                    $map->isCancelledLesson($lesson->getId()) => BookingOccurrence::STATUS_CANCELLED,
                    $map->past->hasKey($lesson->getId()) => BookingOccurrence::STATUS_ATTENDED,
                    $booking->isConfirmed() => BookingOccurrence::STATUS_CONFIRMED,
                    default => BookingOccurrence::STATUS_RESERVED,
                };
                $rescheduledTo = null;
                $entry = $map->cancelled->get($lesson->getId(), null);
                if ($entry instanceof RescheduledLesson) {
                    foreach ($booking->getLessons() as $candidate) {
                        if (!$candidate->getId()->equals($entry->lessonId)) {
                            continue;
                        }

                        $rescheduledTo = $candidate;
                        break;
                    }
                }
                $booking->backfillOccurrence($lesson, $status, $rescheduledTo);
                ++$created;
            }

            if ($booking->getOccurrences()->count() !== $booking->getLessonsMap()->count()) {
                ++$mismatches;
            }
        }
        $this->em->flush();

        return ['bookings' => $processed, 'created' => $created, 'mismatches' => $mismatches];
    }
}
