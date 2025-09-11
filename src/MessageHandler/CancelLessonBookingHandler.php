<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\CancelLessonBooking;
use App\Repository\BookingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class CancelLessonBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        #[Autowire(service: 'state_machine.booking')]
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

        // Ensure the target lesson exists within this booking
        $bookedLesson = $booking->getBookedLesson($command->getLessonId()->toRfc4122());
        if ($bookedLesson === null) {
            $this->logger->error('Lesson not found in booking', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'lessonId' => $command->getLessonId()
                    ->toRfc4122(),
                'cancelledById' => $command->getCancelledBy()
                    ->getId(),
            ]);
            return;
        }

        // Always use plain cancel here; refund is handled by a separate command/handler
        $transition = 'cancel';

        if (! $this->bookingStateMachine->can($booking, $transition)) {
            $this->logger->error('Cannot apply cancel transition to booking', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'status' => $booking->getStatus(),
            ]);
            throw new \RuntimeException(sprintf('Cannot %s this booking in its current state', $transition));
        }

        // Perform domain operation: cancel the specific lesson ONLY (do not cancel the whole booking)
        $wasCancelled = $booking->cancelLesson($command->getLessonId()->toRfc4122(), $command->getReason());

        if (! $wasCancelled) {
            $this->logger->warning('Requested lesson was not active or could not be cancelled', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'lessonId' => $command->getLessonId()
                    ->toRfc4122(),
                'cancelledById' => $command->getCancelledBy()
                    ->getId(),
            ]);
            return;
        }

        // If no active lessons remain, mark the whole booking as cancelled
        if (! $booking->hasActiveBookedLessons()) {
            $booking->setStatus(Booking::STATUS_CANCELLED);
        }

        $this->logger->info('Lesson cancelled within booking', [
            'bookingId' => $booking->getId()
                ->toRfc4122(),
            'lessonId' => $command->getLessonId()
                ->toRfc4122(),
            'cancelledById' => $command->getCancelledBy()
                ->getId(),
            'reason' => $command->getReason(),
            'bookingStatus' => $booking->getStatus(),
        ]);
    }
}
