<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\RefundLessonBooking;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefundLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(RefundLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        
        if (!$booking) {
            $this->logger->error('Booking not found for refund', ['bookingId' => $command->getBookingId()]);
            return;
        }
        
        if ($booking->isRefunded()) {
            $this->logger->info('Booking already refunded', ['bookingId' => $booking->getId()]);
            return;
        }
        
        $lesson = $booking->getLesson();
        if (!$lesson || $lesson->getId() !== $command->getLessonId()) {
            $this->logger->error('Lesson not found or does not match booking for refund', [
                'bookingId' => $booking->getId(),
                'lessonId' => $command->getLessonId()
            ]);
            return;
        }
        
        try {
            // Process the refund (this is a placeholder - integrate with your payment processor)
            $refundAmount = $this->processRefund($booking, $command->getRefundedById());
            
            // Mark the booking as refunded
            $booking->refund($command->getReason(), $refundAmount, $command->getRefundedById());
            
            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            
            $this->logger->info('Booking refund processed successfully', [
                'bookingId' => $booking->getId(),
                'refundAmount' => $refundAmount,
                'refundedById' => $command->getRefundedById()
            ]);
            
            // Here you can add additional logic like sending notifications, etc.
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to process booking refund', [
                'bookingId' => $booking->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Process the refund with the payment processor.
     * This is a placeholder - implement according to your payment processor's API.
     */
    private function processRefund(Booking $booking, int $refundedById): float
    {
        // TODO: Implement actual refund logic with your payment processor
        // This is just a placeholder that returns the full amount
        
        $refundAmount = $booking->getAmountPaid();
        
        // Log the refund for now - in a real implementation, this would call the payment processor's API
        $this->logger->info('Processing refund', [
            'bookingId' => $booking->getId(),
            'amount' => $refundAmount,
            'refundedBy' => $refundedById
        ]);
        
        return $refundAmount;
    }
}
