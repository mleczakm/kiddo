<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\MessageType;
use App\Entity\Payment;
use App\Entity\UserMessage;
use App\Message\RefundLessonBooking;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class RefundLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'state_machine.booking')]
        private readonly WorkflowInterface $bookingStateMachine,
        #[Autowire(service: 'state_machine.payment')]
        private readonly WorkflowInterface $paymentStateMachine,
    ) {}

    public function __invoke(RefundLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());

        if (! $booking) {
            $this->logger->error('Booking not found for refund', [
                'bookingId' => $command->getBookingId(),
                'refundedById' => $command->getRefundedBy()
                    ->getId(),
            ]);
            return;
        }

        // Ensure the target lesson exists within this booking
        $bookedLesson = $booking->getBookedLesson($command->getLessonId()->toRfc4122());
        if ($bookedLesson === null) {
            $this->logger->error('Lesson not found in booking for refund', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'lessonId' => $command->getLessonId()
                    ->toRfc4122(),
                'refundedById' => $command->getRefundedBy()
                    ->getId(),
            ]);
            return;
        }

        $lesson = null;
        foreach ($booking->getLessons() as $booked) {
            if ($booked->getId()->equals($command->getLessonId())) {
                $lesson = $booked;
                break;
            }
        }

        $isAdmin = in_array('ROLE_ADMIN', $command->getRefundedBy()->getRoles(), true);
        if ($lesson !== null && ! $isAdmin && ! $booking->canRequestRefundForLesson($lesson)) {
            $this->logger->warning('Refund blocked within 24h of the lesson', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'lessonId' => $command->getLessonId()
                    ->toRfc4122(),
                'refundedById' => $command->getRefundedBy()
                    ->getId(),
            ]);
            throw new \RuntimeException('Refund is not available within 24h of the lesson');
        }

        // Check if we can request a refund
        if (! $this->bookingStateMachine->can($booking, 'request_refund')) {
            $this->logger->error('Cannot apply refund transition to booking', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'status' => $booking->getStatus(),
            ]);
            throw new \RuntimeException('Cannot request refund for this booking in its current state');
        }

        // Apply the refund request transition with per-lesson context
        $this->bookingStateMachine->apply($booking, 'request_refund', [
            'lessonId' => $command->getLessonId(),
            'reason' => $command->getReason(),
            'by' => $command->getRefundedBy()
                ->getId(),
        ]);

        // Perform domain operation: mark the specific lesson refunded
        $booking->refundLesson($command->getLessonId()->toRfc4122(), $command->getReason());

        $payment = $booking->getPayment();
        if ($payment instanceof Payment && $payment->getStatus() === Payment::STATUS_PAID) {
            $this->paymentStateMachine->apply($payment, Payment::TRANSITION_REQUEST_REFUND);
            $payment->recordRefundRequest($command->getRefundedBy(), $command->getReason(), ! $isAdmin);
        }

        if (! $isAdmin) {
            $message = new UserMessage(
                user: $booking->getUser(),
                subject: sprintf('Prośba o zwrot: %s', $lesson?->getMetadata()->title ?? 'zajęcia'),
                message: $command->getReason() ?: 'Użytkownik poprosił o zwrot płatności za wybrane zajęcia.',
                type: MessageType::REFUND_REQUEST,
            );
            $message->setRelatedBooking($booking);
            $message->setRelatedLesson($lesson);
            $this->entityManager->persist($message);
        }

        // Log outcome
        $this->logger->info('Refund requested for a lesson within booking', [
            'bookingId' => $booking->getId()
                ->toRfc4122(),
            'lessonId' => $command->getLessonId()
                ->toRfc4122(),
            'refundedById' => $command->getRefundedBy()
                ->getId(),
            'reason' => $command->getReason(),
        ]);
    }
}
