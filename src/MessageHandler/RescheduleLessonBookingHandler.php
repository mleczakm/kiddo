<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\RescheduleLessonBooking;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RescheduleLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(RescheduleLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        
        if (!$booking) {
            $this->logger->error('Booking not found for rescheduling', ['bookingId' => $command->getBookingId()]);
            return;
        }
        
        if ($booking->isCancelled()) {
            $this->logger->error('Cannot reschedule a cancelled booking', ['bookingId' => $booking->getId()]);
            return;
        }
        
        $oldLesson = $booking->getLesson();
        if (!$oldLesson || $oldLesson->getId() !== $command->getOldLessonId()) {
            $this->logger->error('Original lesson not found or does not match booking', [
                'bookingId' => $booking->getId(),
                'oldLessonId' => $command->getOldLessonId()
            ]);
            return;
        }
        
        $newLesson = $this->lessonRepository->find($command->getNewLessonId());
        if (!$newLesson) {
            $this->logger->error('New lesson not found for rescheduling', [
                'bookingId' => $booking->getId(),
                'newLessonId' => $command->getNewLessonId()
            ]);
            return;
        }
        
        // Check if the new lesson has available spots
        if ($newLesson->isFullyBooked()) {
            $this->logger->error('Cannot reschedule to a fully booked lesson', [
                'bookingId' => $booking->getId(),
                'newLessonId' => $newLesson->getId()
            ]);
            return;
        }
        
        try {
            // Create a new booking for the new lesson
            $newBooking = clone $booking;
            $newBooking->setLesson($newLesson);
            $newBooking->setRescheduledFrom($booking);
            $newBooking->setRescheduledAt(new \DateTimeImmutable());
            $newBooking->setRescheduledBy($command->getRescheduledById());
            $newBooking->setRescheduleReason($command->getReason());
            
            // Cancel the old booking
            $booking->cancel(sprintf('Rescheduled to lesson #%d. %s', 
                $newLesson->getId(),
                $command->getReason() ?? ''
            ));
            
            $this->entityManager->persist($newBooking);
            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            
            $this->logger->info('Booking rescheduled successfully', [
                'bookingId' => $booking->getId(),
                'oldLessonId' => $oldLesson->getId(),
                'newLessonId' => $newLesson->getId(),
                'rescheduledById' => $command->getRescheduledById()
            ]);
            
            // Here you can add additional logic like sending notifications, etc.
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to reschedule booking', [
                'bookingId' => $booking->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
