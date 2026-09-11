<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\Command\Notification\SendRescheduleAdminNotificationCommand;
use App\Application\Service\InAppNotificationService;
use App\Application\Workflow\BookingStateMachineInterface;
use App\Entity\NotificationSeverity;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Message\RescheduleLessonBooking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class RescheduleLessonBookingHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private LessonRepository $lessonRepository,
        private LoggerInterface $logger,
        private BookingStateMachineInterface $bookingStateMachine,
        private MessageBusInterface $bus,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(RescheduleLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        if (!$booking) {
            $this->logger->error('Booking not found for rescheduling', [
                'bookingId' => $command->getBookingId(),
                'rescheduledById' => $command->getRescheduledBy()->getId(),
            ]);
            return;
        }

        $oldLesson = $this->lessonRepository->find($command->getOldLessonId());
        if (!$oldLesson || !$booking->getLessons()->contains($oldLesson)) {
            $this->logger->error('Old lesson not found in booking', [
                'bookingId' => $booking->getId(),
                'oldLessonId' => $command->getOldLessonId(),
                'rescheduledById' => $command->getRescheduledBy()->getId(),
            ]);
            return;
        }

        // Rescheduling is only allowed for active lessons
        if (
            !(
                $booking->findOccurrence($oldLesson->getId())?->isActive() ?? $booking->getLessonsMap()->isActiveLesson(
                    $oldLesson->getId(),
                )
            )
        ) {
            $this->logger->warning('Attempt to reschedule a non-active lesson ignored', [
                'bookingId' => $booking->getId(),
                'oldLessonId' => $command->getOldLessonId(),
                'status' => $booking->getStatus(),
            ]);
            return;
        }

        $newLesson = $this->lessonRepository->find($command->getNewLessonId());
        if (!$newLesson) {
            $this->logger->error('New lesson not found', [
                'newLessonId' => $command->getNewLessonId(),
                'rescheduledById' => $command->getRescheduledBy()->getId(),
            ]);
            return;
        }

        $isAdmin = in_array('ROLE_ADMIN', $command->getRescheduledBy()->getRoles(), true);
        if (!$isAdmin && !$booking->canRescheduleLesson($oldLesson)) {
            $this->logger->warning('Reschedule blocked by ticket policy or 24h rule', [
                'bookingId' => $booking->getId(),
                'oldLessonId' => $command->getOldLessonId(),
                'policy' => $booking->getReschedulePolicyFor($oldLesson)->value,
                'rescheduledById' => $command->getRescheduledBy()->getId(),
            ]);
            throw new \RuntimeException('Reschedule is not allowed for this booking');
        }

        if ($newLesson->getAvailableSpots() <= 0) {
            $this->logger->warning('Reschedule target lesson has no available spots', [
                'bookingId' => $booking->getId(),
                'newLessonId' => $command->getNewLessonId(),
            ]);
            throw new \RuntimeException('Selected lesson has no available spots');
        }

        // Check workflow transition first
        if (!$this->bookingStateMachine->can($booking, 'reschedule')) {
            $this->logger->error('Cannot apply reschedule transition to booking', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'status' => $booking->getStatus(),
            ]);
            throw new \RuntimeException('Cannot reschedule this booking in its current state');
        }

        // Apply workflow transition with context
        $this->bookingStateMachine->apply($booking, 'reschedule', [
            'oldLessonId' => $command->getOldLessonId(),
            'newLessonId' => $command->getNewLessonId(),
            'reason' => $command->getReason(),
            'by' => $command->getRescheduledBy()->getId(),
        ]);

        // Perform domain reschedule operation on lessons map
        $booking->rescheduleLesson($oldLesson, $newLesson, $command->getRescheduledBy());

        // Notify the finance contacts and instructors connected to either
        // occurrence about the reschedule.
        $this->bus->dispatch(new SendRescheduleAdminNotificationCommand(
            booking: $booking,
            oldLesson: $oldLesson,
            newLesson: $newLesson,
            rescheduledBy: $command->getRescheduledBy(),
            reason: $command->getReason(),
        ));

        $oldTitle = $oldLesson->getMetadata()->title;
        $newTitle = $newLesson->getMetadata()->title;

        // Notify the customer in-app that their booking moved (the internal
        // notification above covers the responsible staff recipients).
        $this->inAppNotifications->notify(
            $booking->getUser(),
            $this->translator->trans('notifications.in_app.reschedule.user.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.reschedule.user.body',
                [
                    'from' => $oldTitle,
                    'to' => $newTitle,
                ],
                'messages',
            ),
            $this->urlGenerator->generate('dashboard'),
            NotificationSeverity::Info,
        );
    }
}
