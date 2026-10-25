<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckExpiredBookings;
use App\Application\Repository\BookingRepositoryInterface;
use App\Application\Service\ActivityLogger;
use App\Application\Workflow\BookingStateMachineInterface;
use App\Entity\ActivityType;
use App\Entity\Payment;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CheckExpiredBookingsHandler
{
    private const string CANCELLATION_REASON = 'Rezerwacja anulowana automatycznie — brak płatności w wymaganym terminie';

    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingStateMachineInterface $bookingStateMachine,
        private readonly ActivityLogger $activityLogger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(CheckExpiredBookings $command): void
    {
        // Backstop for bookings whose payment was never expired (the payment
        // sweep, CheckExpiredPaymentsHandler, normally cancels them first).
        $cutoff = $command->referenceTime->modify(sprintf('-%d minutes', Payment::VALIDITY_MINUTES));
        $candidates = $this->bookingRepository->findExpiredPendingBookings($cutoff);

        foreach ($candidates as $booking) {
            if ($booking->hasPaidPayment()) {
                continue;
            }

            if (!$this->bookingStateMachine->can($booking, 'cancel')) {
                continue;
            }

            // Apply the workflow transition first so that
            // workflow.booking.transition.cancel fires (sends the
            // cancellation notification) while the guard still allows it;
            // markAllLessonsCancelled() only records who/why afterwards and
            // does not touch status, so it can safely run either side of it.
            $this->bookingStateMachine->apply($booking, 'cancel');
            $booking->markAllLessonsCancelled(self::CANCELLATION_REASON);

            $user = $booking->getUser();
            $userId = $user->getId();

            $this->activityLogger->log(
                type: ActivityType::BOOKING_AUTO_CANCELLED,
                title: sprintf('Rezerwacja %s wygasła automatycznie', $user->getName()),
                subject: $user,
                summary: self::CANCELLATION_REASON,
                url: $userId !== null
                    ? $this->urlGenerator->generate('app_admin_user_view', [
                        'id' => $userId,
                    ]) : null,
                context: [
                    'bookingId' => (string) $booking->getId(),
                ],
            );
        }
    }
}
