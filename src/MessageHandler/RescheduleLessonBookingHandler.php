<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Message\RescheduleLessonBooking;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class RescheduleLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly WorkflowInterface $bookingStateMachine
    ) {}

    public function __invoke(RescheduleLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());

        if (! $booking) {
            $this->logger->error('Booking not found for rescheduling', [
                'bookingId' => $command->getBookingId(),
                'rescheduledById' => $command->getRescheduledBy()
                    ->getId(),
            ]);
            return;
        }

        if ($booking->isCancelled()) {
            $this->logger->error('Cannot reschedule a cancelled booking', [
                'bookingId' => $booking->getId(),
                'rescheduledById' => $command->getRescheduledBy()
                    ->getId(),
            ]);
            return;
        }

        $oldLesson = $this->lessonRepository->find($command->getOldLessonId());
        if (! $oldLesson || ! $booking->getLessons()->contains($oldLesson)) {
            $this->logger->error('Old lesson not found in booking', [
                'bookingId' => $booking->getId(),
                'oldLessonId' => $command->getOldLessonId(),
                'rescheduledById' => $command->getRescheduledBy()
                    ->getId(),
            ]);
            return;
        }

        $newLesson = $this->lessonRepository->find($command->getNewLessonId());
        if (! $newLesson) {
            $this->logger->error('New lesson not found', [
                'newLessonId' => $command->getNewLessonId(),
                'rescheduledById' => $command->getRescheduledBy()
                    ->getId(),
            ]);
            return;
        }

        if ($newLesson->getAvailableSpots() <= 0) {
            $this->logger->error('No available spots in the new lesson', [
                'newLessonId' => $newLesson->getId(),
                'availableSpots' => $newLesson->getAvailableSpots(),
                'rescheduledById' => $command->getRescheduledBy()
                    ->getId(),
            ]);
            return;
        }
        $oldLessons = $booking->getLessons()
            ->toArray();

        $lessons = array_merge(
            [$newLesson],
            array_filter($oldLessons, fn(Lesson $lesson) => $lesson->getId() !== $oldLesson->getId())
        );

        // Create a new booking for the new lesson with the same user and payment
        $newBooking = new Booking($booking->getUser(), $booking->getPayment(), ... $lessons);

        // Copy relevant data from the old booking
        $newBooking->setNotes('Rescheduled from booking #' . $booking->getId()->toRfc4122());
        $newBooking->setCreatedAt(new \DateTimeImmutable());
        $newBooking->setUpdatedAt(new \DateTimeImmutable());
        $newBooking->setStatus(Booking::STATUS_CONFIRMED);

        // Set reschedule metadata on the new booking
        $newBooking->setRescheduledFrom($oldLesson);
        $newBooking->setRescheduledBy($command->getRescheduledBy());
        $newBooking->setRescheduleReason($command->getReason() ?? 'Rescheduled to new lesson');

        // Apply workflow transition to cancel the old booking with reschedule reason
        if (! $this->bookingStateMachine->can($booking, 'reschedule')) {
            $this->logger->error('Cannot apply reschedule transition to the old booking', [
                'bookingId' => $booking->getId(),
                'currentState' => $booking->getStatus(),
            ]);
            return;
        }

        $this->bookingStateMachine->apply($booking, 'reschedule', [
            'reason' => 'rescheduled',
            'rescheduled_by' => $command->getRescheduledBy()
                ->getId(),
            'rescheduled_to_lesson' => $newLesson->getId()
                ->toRfc4122(),
        ]);

        $this->entityManager->persist($newBooking);

        $this->logger->info('Booking rescheduled successfully', [
            'oldBookingId' => $booking->getId(),
            'newBookingId' => $newBooking->getId(),
            'rescheduledById' => $command->getRescheduledBy()
                ->getId(),
        ]);
    }
}
