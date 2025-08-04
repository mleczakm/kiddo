<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CancelLessonBooking;
use App\Entity\User;
use App\Repository\BookingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class CancelLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly WorkflowInterface $bookingStateMachine,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(CancelLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());

        if (! $booking) {
            $this->logger->error('Booking not found', [
                'bookingId' => $command->getBookingId(),
                'cancelledById' => $command->getCancelledBy()
                    ->getId(),
            ]);
            return;
        }

        if ($booking->isCancelled()) {
            $this->logger->info('Booking already cancelled', [
                'bookingId' => $booking->getId(),
                'cancelledById' => $command->getCancelledBy()
                    ->getId(),
            ]);
            return;
        }

        $lesson = $booking->getLesson();
        if (! $lesson || $lesson->getId() !== $command->getLessonId()) {
            $this->logger->error('Lesson not found in booking', [
                'bookingId' => $booking->getId(),
                'lessonId' => $command->getLessonId(),
                'cancelledById' => $command->getCancelledBy()
                    ->getId(),
            ]);
            return;
        }

        // Determine the appropriate transition based on the reason
        $transition = $command->isRefundRequested() ? 'request_refund' : 'cancel';

        // Check if we can apply the transition
        if (! $this->bookingStateMachine->can($booking, $transition)) {
            $this->logger->error('Cannot apply cancel transition to booking', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'status' => $booking->getStatus(),
                'availableTransitions' => $this->bookingStateMachine->getEnabledTransitions($booking),
            ]);
            throw new \RuntimeException(sprintf('Cannot %s this booking in its current state', $transition));
        }

        // Apply the transition
        $this->bookingStateMachine->apply($booking, $transition, [
            'reason' => $command->getReason(),
        ]);

        // Update booking with cancellation details
        $booking->setCancelledBy($command->getCancelledBy());
        $booking->setUpdatedAt(new \DateTimeImmutable());

        // Add note with cancellation details
        $note = sprintf("Booking cancelled.\nReason: %s", $command->getReason() ?? 'No reason provided');

        // If this is a refund request, add refund information
        if ($command->isRefundRequested()) {
            $refundAmount = $booking->getAmountPaid(); // Mocked amount, to be implemented later
            $note .= sprintf("\n\nRefund requested.\nSuggested amount to be refunded: %.2f PLN", $refundAmount / 100);
        }

        $booking->setNotes($note);

        $this->logger->info(
            sprintf('Booking %s successfully', $command->isRefundRequested() ? 'refund requested' : 'cancelled'),
            [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'lessonId' => $lesson->getId()
                    ->toRfc4122(),
                'cancelledById' => $command->getCancelledBy()
                    ->getId(), // User ID is an integer, not a Ulid
                'reason' => $command->getReason(),
                'isRefundRequest' => $command->isRefundRequested(),
            ]
        );
    }
}
