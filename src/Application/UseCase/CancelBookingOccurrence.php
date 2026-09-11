<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\OfferWaitlistSeats;
use App\Application\Repository\BookingRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\BookingNotificationRecipients;
use App\Application\Service\InAppNotificationService;
use App\Application\Workflow\BookingStateMachineInterface;
use App\Entity\NotificationSeverity;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Canonical use case for cancelling one lesson occurrence within a booking
 * (without requesting a refund). CancelLessonBookingHandler is a thin
 * Messenger adapter over this class.
 */
final readonly class CancelBookingOccurrence
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private UserRepositoryInterface $userRepository,
        private BookingStateMachineInterface $bookingStateMachine,
        private LoggerInterface $logger,
        private InAppNotificationService $inAppNotifications,
        private BookingNotificationRecipients $notificationRecipients,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private MessageBusInterface $bus,
    ) {}

    /**
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function __invoke(Ulid $bookingId, Ulid $lessonId, int $cancelledByUserId, ?string $reason): void
    {
        $cancelledBy = $this->userRepository->find($cancelledByUserId);
        if ($cancelledBy === null) {
            throw new \InvalidArgumentException(sprintf('User %d not found', $cancelledByUserId));
        }

        $booking = $this->bookingRepository->find($bookingId);

        if (!$booking) {
            $this->logger->error('Booking not found', [
                'bookingId' => $bookingId,
                'cancelledById' => $cancelledBy->getId(),
            ]);
            return;
        }

        // Ensure the target lesson exists within this booking
        if (
            $booking->findOccurrence($lessonId) === null
            && $booking->getBookedLesson($lessonId->toRfc4122()) === null
        ) {
            $this->logger->error('Lesson not found in booking', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'lessonId' => $lessonId->toRfc4122(),
                'cancelledById' => $cancelledBy->getId(),
            ]);
            return;
        }

        // Always use plain cancel here; refund is handled by a separate use case
        $transition = 'cancel';

        // A cancel request for a booking that is already cancelled (a retried
        // message, a double-submit, a stale modal, or a concurrent cancel that
        // won the race) is a no-op, not an error: the desired end state is
        // already in place. Anything else that blocks the transition is
        // unexpected and worth a warning, but still must not blow up a
        // fire-and-forget message with a fatal HandlerFailedException.
        if (!$this->bookingStateMachine->can($booking, $transition)) {
            $context = [
                'bookingId' => $booking->getId()->toRfc4122(),
                'lessonId' => $lessonId->toRfc4122(),
                'status' => $booking->getStatus(),
                'transition' => $transition,
            ];

            if ($booking->isCancelled()) {
                $this->logger->info('Skipping booking cancel — booking already cancelled', $context);

                return;
            }

            $this->logger->warning('Skipping booking cancel — transition not applicable', $context);

            return;
        }

        // Perform domain operation: cancel the specific lesson ONLY (do not cancel the whole booking)
        $wasCancelled = $booking->cancelLesson($lessonId->toRfc4122(), $reason, $cancelledBy);

        if (!$wasCancelled) {
            $this->logger->warning('Requested lesson was not active or could not be cancelled', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'lessonId' => $lessonId->toRfc4122(),
                'cancelledById' => $cancelledBy->getId(),
            ]);
            return;
        }

        // A seat just freed on this occurrence — after this cancel commits, offer
        // it to the lesson's waitlist. No-ops when the waitlist feature is off.
        $this->bus->dispatch(new Envelope(new OfferWaitlistSeats($lessonId))->with(new DispatchAfterCurrentBusStamp()));

        // If no active lessons remain, mark the whole booking as cancelled. Apply the
        // workflow transition (instead of setting the status directly) so that
        // workflow.booking.transition.cancel fires and the cancellation notification
        // (email + in-app, to both the customer and admins) actually gets sent.
        $wholeBookingCancelled = !$booking->hasActiveBookedLessons();
        if ($wholeBookingCancelled) {
            $this->bookingStateMachine->apply($booking, 'cancel');
        }

        $lessonTitle = '';
        $cancelledLesson = null;
        foreach ($booking->getLessons() as $lesson) {
            if (!$lesson->getId()->equals($lessonId)) {
                continue;
            }

            $lessonTitle = $lesson->getMetadata()->title;
            $cancelledLesson = $lesson;
            break;
        }

        if ($cancelledLesson !== null && !$wholeBookingCancelled) {
            $recipients = $this->notificationRecipients->forLessons([$cancelledLesson], exclude: $cancelledBy);
            $this->inAppNotifications->notifyUsers(
                $recipients,
                $this->translator->trans('notifications.in_app.cancellation.instructor.title', [], 'messages'),
                $this->translator->trans(
                    'notifications.in_app.cancellation.instructor.body',
                    [
                        'name' => $booking->getUser()->getName(),
                        'lesson' => $lessonTitle,
                        'date' => $cancelledLesson->schedule->format('Y-m-d H:i'),
                    ],
                    'messages',
                ),
                $this->urlGenerator->generate('app_admin_lesson_view', [
                    'id' => (string) $cancelledLesson->getId(),
                ]),
                NotificationSeverity::Info,
            );
        }

        $this->logger->info('Lesson cancelled within booking', [
            'bookingId' => $booking->getId()->toRfc4122(),
            'lessonId' => $lessonId->toRfc4122(),
            'cancelledById' => $cancelledBy->getId(),
            'reason' => $reason,
            'bookingStatus' => $booking->getStatus(),
        ]);
    }
}
