<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Entity\User;
use App\Message\RefundLessonBooking;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class RefundLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly WorkflowInterface $bookingStateMachine
    ) {}

    public function __invoke(RefundLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());

        if (! $booking) {
            $this->logger->error('Booking not found for refund', [
                'bookingId' => $command->getBookingId(),
                'refundedById' => $command->getRefundedById(),
            ]);
            return;
        }

        $lesson = $booking->getLesson();
        if (! $lesson) {
            $this->logger->error('No lesson found in booking', [
                'bookingId' => $booking->getId(),
                'refundedById' => $command->getRefundedById(),
            ]);
            return;
        }

        // Check if we can request a refund
        if (! $this->bookingStateMachine->can($booking, 'request_refund')) {
            $this->logger->error('Cannot apply refund transition to booking', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'status' => $booking->getStatus(),
                'availableTransitions' => $this->bookingStateMachine->getEnabledTransitions($booking),
            ]);
            throw new \RuntimeException('Cannot request refund for this booking in its current state');
        }

        // Apply the refund request transition
        $this->bookingStateMachine->apply($booking, 'request_refund', [
            'reason' => $command->getReason(),
        ]);

        // Update booking with refund details
        $refundedBy = $this->entityManager->getReference(User::class, $command->getRefundedById());
        $booking->setRefundedBy($refundedBy);
        $booking->setUpdatedAt(new \DateTimeImmutable());

        // Add note with refund details
        $refundAmount = $booking->getAmountPaid(); // Mocked amount, to be implemented later
        $note = sprintf(
            "Refund requested.\nReason: %s\nSuggested amount to be refunded: %.2f PLN",
            $command->getReason() ?? 'No reason provided',
            $refundAmount / 100 // Convert from cents to PLN
        );
        $booking->setNotes($note);

        $this->logger->info('Booking refund requested successfully', [
            'bookingId' => $booking->getId()
                ->toRfc4122(),
            'lessonId' => $lesson->getId()
                ->toRfc4122(),
            'refundedById' => $command->getRefundedById()
                ->toRfc4122(),
            'reason' => $command->getReason(),
            'refundAmount' => $refundAmount,
        ]);
    }
}
