<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Repository\BookingRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Workflow\BookingStateMachineInterface;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\Payment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Canonical use case for requesting a refund on one lesson within a booking.
 * RefundLessonBookingHandler is a thin Messenger adapter over this class.
 */
final readonly class RequestRefund
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
        private BookingStateMachineInterface $bookingStateMachine,
        private PaymentStateMachineInterface $paymentStateMachine,
    ) {}

    public function __invoke(Ulid $bookingId, Ulid $lessonId, int $refundedByUserId, ?string $reason): void
    {
        $refundedBy = $this->userRepository->find($refundedByUserId);
        if ($refundedBy === null) {
            throw new \InvalidArgumentException(sprintf('User %d not found', $refundedByUserId));
        }

        $booking = $this->bookingRepository->find($bookingId);

        if (!$booking) {
            $this->logger->error('Booking not found for refund', [
                'bookingId' => $bookingId,
                'refundedById' => $refundedBy->getId(),
            ]);
            return;
        }

        // Ensure the target lesson exists within this booking
        $bookedLesson = $booking->getBookedLesson($lessonId->toRfc4122());
        if ($bookedLesson === null) {
            $this->logger->error('Lesson not found in booking for refund', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'lessonId' => $lessonId->toRfc4122(),
                'refundedById' => $refundedBy->getId(),
            ]);
            return;
        }

        $lesson = null;
        foreach ($booking->getLessons() as $booked) {
            if (!$booked->getId()->equals($lessonId)) {
                continue;
            }

            $lesson = $booked;
            break;
        }

        $isAdmin = in_array('ROLE_ADMIN', $refundedBy->getRoles(), true);
        if ($lesson !== null && !$isAdmin && !$booking->canRequestRefundForLesson($lesson)) {
            $this->logger->warning('Refund blocked within 24h of the lesson', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'lessonId' => $lessonId->toRfc4122(),
                'refundedById' => $refundedBy->getId(),
            ]);
            throw new \RuntimeException('Refund is not available within 24h of the lesson');
        }

        // Check if we can request a refund
        if (!$this->bookingStateMachine->can($booking, 'request_refund')) {
            $this->logger->error('Cannot apply refund transition to booking', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'status' => $booking->getStatus(),
            ]);
            throw new \RuntimeException('Cannot request refund for this booking in its current state');
        }

        // Apply the refund request transition with per-lesson context
        $this->bookingStateMachine->apply($booking, 'request_refund', [
            'lessonId' => $lessonId,
            'reason' => $reason,
            'by' => $refundedBy->getId(),
        ]);

        // Perform domain operation: mark the specific lesson refunded
        $booking->refundLesson($lessonId->toRfc4122(), $reason);

        $payment = $booking->getPayment();
        if ($payment instanceof Payment && $payment->getStatus() === Payment::STATUS_PAID) {
            $this->paymentStateMachine->apply($payment, Payment::TRANSITION_REQUEST_REFUND);
            $payment->recordRefundRequest($refundedBy, $reason, !$isAdmin);
        }

        // Log outcome
        $this->logger->info('Refund requested for a lesson within booking', [
            'bookingId' => $booking->getId()->toRfc4122(),
            'lessonId' => $lessonId->toRfc4122(),
            'refundedById' => $refundedBy->getId(),
            'reason' => $reason,
        ]);
    }
}
