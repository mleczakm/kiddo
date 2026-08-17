<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Command\AddBooking;
use App\Application\Command\Notification\NewUser;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Application\Service\ActivityLogger;
use App\Entity\ActivityType;
use App\Message\CancelLessonBooking;
use App\Message\ReactivateBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generic activity-feed instrumentation for the command bus: after a command
 * actually finishes handling (checked via HandledStamp, so this fires exactly
 * once — never on the "routed to async transport" pass, only when a handler
 * really ran, whether that's inline/sync or later in the worker), a handful
 * of "interesting to a CRM operator" commands get turned into an ActivityLog
 * entry. Add a new `match` arm here to log a new command type; nothing needs
 * to change in the handler itself.
 *
 * Code paths that never go through the bus at all (e.g. LiveComponent
 * actions that mutate entities directly) are instrumented separately via
 * direct ActivityLogger calls — see UpcomingAttendeesComponent.
 */
final readonly class ActivityLogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
        private LessonRepository $lessonRepository,
        private BookingRepository $bookingRepository,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()
            ->handle($envelope, $stack);

        if ($envelope->last(HandledStamp::class) === null) {
            // Only routed to a transport so far (e.g. queued for async); the
            // real handling — and the activity it represents — hasn't happened yet.
            return $envelope;
        }

        $this->logFor($envelope->getMessage());

        return $envelope;
    }

    private function logFor(object $message): void
    {
        match (true) {
            $message instanceof AddBooking => $this->logBookingCreated($message),
            $message instanceof CancelLessonBooking => $this->logBookingCancelled($message),
            $message instanceof RescheduleLessonBooking => $this->logBookingRescheduled($message),
            $message instanceof ReactivateBooking => $this->logBookingReactivated($message),
            $message instanceof RefundLessonBooking => $this->logRefundRequested($message),
            $message instanceof NewUser => $this->logUserRegistered($message),
            $message instanceof TransferNotMatchedCommand => $this->logTransferUnmatched($message),
            default => null,
        };
    }

    private function logBookingCreated(AddBooking $command): void
    {
        $user = $this->userRepository->find($command->userId);
        $lesson = $this->lessonRepository->find($command->lessonId);
        if ($user === null || $lesson === null) {
            return;
        }

        $userId = $user->getId();

        $this->activityLogger->log(
            type: ActivityType::BOOKING_CREATED,
            title: sprintf('%s zarezerwował/a zajęcia', $user->getName()),
            subject: $user,
            summary: $lesson->getMetadata()
                ->title,
            url: $userId !== null ? $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $userId,
            ]) : null,
            context: [
                'lessonId' => $command->lessonId,
                ...($command->paymentCode !== '' ? [
                    'paymentCode' => $command->paymentCode,
                ] : []),
            ],
        );
    }

    private function logBookingCancelled(CancelLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        $lesson = $this->lessonRepository->find($command->getLessonId());
        if ($booking === null) {
            return;
        }

        $user = $booking->getUser();
        $userId = $user->getId();

        $this->activityLogger->log(
            type: ActivityType::BOOKING_CANCELLED,
            title: sprintf('%s odwołał/a rezerwację', $user->getName()),
            subject: $user,
            summary: $lesson?->getMetadata()
                ->title,
            url: $userId !== null ? $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $userId,
            ]) : null,
            context: [
                'bookingId' => (string) $command->getBookingId(),
            ],
        );
    }

    private function logBookingRescheduled(RescheduleLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        $oldLesson = $this->lessonRepository->find($command->getOldLessonId());
        $newLesson = $this->lessonRepository->find($command->getNewLessonId());
        if ($booking === null) {
            return;
        }

        $user = $booking->getUser();
        $userId = $user->getId();

        $this->activityLogger->log(
            type: ActivityType::BOOKING_RESCHEDULED,
            title: sprintf('%s zmienił/a termin rezerwacji', $user->getName()),
            subject: $user,
            summary: $oldLesson !== null && $newLesson !== null
                ? sprintf('%s → %s', $oldLesson->getMetadata()->title, $newLesson->getMetadata()->title)
                : null,
            url: $userId !== null ? $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $userId,
            ]) : null,
            context: [
                'bookingId' => (string) $command->getBookingId(),
            ],
        );
    }

    private function logBookingReactivated(ReactivateBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        if ($booking === null) {
            return;
        }

        $user = $booking->getUser();
        $userId = $user->getId();

        $this->activityLogger->log(
            type: ActivityType::BOOKING_REACTIVATED,
            title: sprintf('Rezerwacja %s została przywrócona', $user->getName()),
            subject: $user,
            summary: $command->getReason(),
            url: $userId !== null ? $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $userId,
            ]) : null,
            context: [
                'bookingId' => (string) $command->getBookingId(),
            ],
        );
    }

    private function logRefundRequested(RefundLessonBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());
        $lesson = $this->lessonRepository->find($command->getLessonId());
        if ($booking === null) {
            return;
        }

        $user = $booking->getUser();
        $userId = $user->getId();

        $this->activityLogger->log(
            type: ActivityType::REFUND_REQUESTED,
            title: sprintf('%s poprosił/a o zwrot', $user->getName()),
            subject: $user,
            summary: $lesson?->getMetadata()
                ->title,
            url: $userId !== null ? $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $userId,
            ]) : null,
            context: [
                'bookingId' => (string) $command->getBookingId(),
            ],
        );
    }

    private function logUserRegistered(NewUser $command): void
    {
        // The handler reloads/confirms the user by id; reload here too so we
        // read the post-handling state (name may have been touched) consistently.
        $user = $this->userRepository->find($command->user->getId());
        if ($user === null) {
            return;
        }

        $userId = $user->getId();

        $this->activityLogger->log(
            type: ActivityType::USER_REGISTERED,
            title: sprintf('Nowy użytkownik: %s', $user->getName()),
            subject: $user,
            summary: $user->getEmail(),
            url: $userId !== null ? $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $userId,
            ]) : null,
            dedupeKey: 'user_registered:' . $userId,
        );
    }

    private function logTransferUnmatched(TransferNotMatchedCommand $command): void
    {
        $transfer = $command->transfer;

        $this->activityLogger->log(
            type: ActivityType::TRANSFER_UNMATCHED,
            title: 'Nierozpoznany przelew',
            subject: null,
            summary: sprintf('%s — %s (%s)', $transfer->amount, $transfer->getSender(), $transfer->title),
            url: $this->urlGenerator->generate('app_admin_transfers'),
            context: [
                'transferId' => $transfer->getId(),
            ],
            dedupeKey: 'transfer_unmatched:' . $transfer->getId(),
        );
    }
}
