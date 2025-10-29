<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\Command\Notification\SendRescheduleAdminNotificationCommand;
use App\Message\RescheduleLessonBooking;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
readonly class RescheduleLessonBookingHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private LessonRepository $lessonRepository,
        private LoggerInterface $logger,
        #[Autowire(service: 'state_machine.booking')]
        private WorkflowInterface $bookingStateMachine,
        private MessageBusInterface $bus,
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

        // Rescheduling is only allowed for active lessons
        if (! $booking->getLessonsMap()->isActiveLesson($oldLesson->getId())) {
            $this->logger->warning('Attempt to reschedule a non-active lesson ignored', [
                'bookingId' => $booking->getId(),
                'oldLessonId' => $command->getOldLessonId(),
                'status' => $booking->getStatus(),
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

        // Check workflow transition first
        if (! $this->bookingStateMachine->can($booking, 'reschedule')) {
            $this->logger->error('Cannot apply reschedule transition to booking', [
                'bookingId' => $booking->getId()
                    ->toRfc4122(),
                'status' => $booking->getStatus(),
            ]);
            throw new \RuntimeException('Cannot reschedule this booking in its current state');
        }

        // Apply workflow transition with context
        $this->bookingStateMachine->apply($booking, 'reschedule', [
            'oldLessonId' => $command->getOldLessonId(),
            'newLessonId' => $command->getNewLessonId(),
            'reason' => $command->getReason(),
            'by' => $command->getRescheduledBy()
                ->getId(),
        ]);

        // Perform domain reschedule operation on lessons map
        $booking->rescheduleLesson($oldLesson, $newLesson, $command->getRescheduledBy());

        // Notify admins about reschedule
        $this->bus->dispatch(new SendRescheduleAdminNotificationCommand(
            booking: $booking,
            oldLesson: $oldLesson,
            newLesson: $newLesson,
            rescheduledBy: $command->getRescheduledBy(),
            reason: $command->getReason(),
        ));
    }
}
