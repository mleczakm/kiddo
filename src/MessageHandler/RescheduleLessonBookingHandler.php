<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Entity\User;
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

        $oldLesson = $booking->getLesson();
        if (! $oldLesson || $oldLesson->getId() !== $command->getOldLessonId()) {
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

        // Create a new booking for the new lesson with the same user and payment
        $newBooking = new Booking(user: $booking->getUser(), payment: $booking->getPayment(), lessons: $newLesson);

        // Copy relevant data from the old booking
        $newBooking->setNotes('Rescheduled from booking #' . $booking->getId()->toRfc4122());
        $newBooking->setCreatedAt(new \DateTimeImmutable());
        $newBooking->setUpdatedAt(new \DateTimeImmutable());

        // Set reschedule metadata on the new booking
        $newBooking->setRescheduledFrom($oldLesson);
        $newBooking->setRescheduledBy($command->getRescheduledBy());
        $newBooking->setRescheduleReason($command->getReason() ?? 'Rescheduled to new lesson');

        // Apply workflow transition to cancel the old booking with reschedule reason
        if (! $this->bookingStateMachine->can($booking, 'reschedule')) {
            $this->logger->error('Cannot apply reschedule transition to booking', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'status' => $booking->getStatus(),
                'availableTransitions' => $this->bookingStateMachine->getEnabledTransitions($booking),
            ]);
            throw new \RuntimeException('Cannot reschedule this booking in its current state');
        }

        // Apply the reschedule transition to the old booking
        $this->bookingStateMachine->apply($booking, 'reschedule', [
            'reason' => 'Rescheduled to new lesson #' . $newLesson->getId()->toRfc4122(),
            'new_booking_id' => $newBooking->getId()
                ->toRfc4122(),
        ]);

        // Update the old booking with reschedule details
        $booking->setRescheduledFrom($oldLesson);
        $booking->setRescheduledBy($command->getRescheduledBy());
        $booking->setRescheduleReason($command->getReason());
        $booking->setUpdatedAt(new \DateTimeImmutable());

        // Persist the new booking
        $this->entityManager->persist($newBooking);

        $this->logger->info('Booking rescheduled successfully', [
            'oldBookingId' => $booking->getId()
                ->toRfc4122(),
            'newBookingId' => $newBooking->getId()
                ->toRfc4122(),
            'oldLessonId' => $oldLesson->getId()
                ->toRfc4122(),
            'newLessonId' => $newLesson->getId()
                ->toRfc4122(),
            'rescheduledById' => $command->getRescheduledBy()
                ->getId(), // User ID is an integer, not a Ulid
            'reason' => $command->getReason(),
        ]);
    }
}
