<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\CancelLessonBooking;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CancelLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CancelLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        
        if (!$booking) {
            $this->logger->error('Booking not found', ['bookingId' => $command->getBookingId()]);
            return;
        }
        
        if ($booking->isCancelled()) {
            $this->logger->info('Booking already cancelled', ['bookingId' => $booking->getId()]);
            return;
        }
        
        $lesson = $booking->getLesson();
        if (!$lesson || $lesson->getId() !== $command->getLessonId()) {
            $this->logger->error('Lesson not found or does not match booking', [
                'bookingId' => $booking->getId(),
                'lessonId' => $command->getLessonId()
            ]);
            return;
        }
        
        try {
            $booking->cancel($command->getReason());
            
            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            
            $this->logger->info('Booking cancelled successfully', [
                'bookingId' => $booking->getId(),
                'cancelledById' => $command->getCancelledById()
            ]);
            
            // Here you can add additional logic like sending notifications, etc.
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel booking', [
                'bookingId' => $booking->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
