<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\MessageType;
use App\Entity\UserMessage;
use App\Message\CancelLessonBooking;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
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

        // If no active lessons remain, mark the whole booking as cancelled. Apply the
        // workflow transition (instead of setting the status directly) so that
        // workflow.booking.transition.cancel fires and the cancellation notification
        // (email + in-app, to both the customer and admins) actually gets sent.
        if (! $booking->hasActiveBookedLessons()) {
            $this->bookingStateMachine->apply($booking, 'cancel');
        }

        $lessonTitle = '';
        foreach ($booking->getLessons() as $lesson) {
            if ($lesson->getId()->equals($command->getLessonId())) {
                $lessonTitle = $lesson->getMetadata()->title;
                break;
            }
        }

        $this->entityManager->persist(new UserMessage(
            user: $booking->getUser(),
            subject: sprintf('Anulowano zajęcia: %s', $lessonTitle),
            message: $command->getReason()
                ? sprintf('Zajęcia "%s" zostały anulowane. Powód: %s', $lessonTitle, $command->getReason())
                : sprintf('Zajęcia "%s" zostały anulowane.', $lessonTitle),
            type: MessageType::CANCELLATION_REQUEST,
        ));

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
