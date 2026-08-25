<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use Aeon\Calendar\TimeUnit;
use App\Application\Command\CheckExpiredBookings;
use App\Application\Repository\BookingRepositoryInterface;
use App\Application\Service\ActivityLogger;
use App\Application\Service\WorkingDaysDeadlineCalculator;
use App\Application\Workflow\BookingStateMachineInterface;
use App\Entity\ActivityType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CheckExpiredBookingsHandler
{
    private const string CANCELLATION_REASON = 'Rezerwacja anulowana automatycznie — brak płatności w wymaganym terminie';

    /**
     * Bookings get 24 working hours (Monday-Friday) to be paid before being
     * auto-cancelled - weekend time doesn't count, since bank transfers
     * can't settle until the next business day.
     */
    private const int EXPIRATION_WORKING_HOURS = 24;

    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingStateMachineInterface $bookingStateMachine,
        private readonly ActivityLogger $activityLogger,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WorkingDaysDeadlineCalculator $workingDaysDeadlineCalculator,
    ) {}

    /**
     * @throws \Aeon\Calendar\Exception\InvalidArgumentException
     */
    public function __invoke(CheckExpiredBookings $command): void
    {
        // A necessary (not sufficient) pre-filter: a booking can only have
        // accrued EXPIRATION_WORKING_HOURS of working time if at least that
        // many real hours have passed, since weekends only pause the clock,
        // never speed it up. The exact per-booking deadline is checked below.
        $naiveCutoff = $command->referenceTime->modify(sprintf('-%d hours', self::EXPIRATION_WORKING_HOURS));
        $candidates = $this->bookingRepository->findExpiredPendingBookings($naiveCutoff);

        foreach ($candidates as $booking) {
            if ($booking->hasPaidPayment()) {
                continue;
            }

            $deadline = $this->workingDaysDeadlineCalculator->addWorkingTime(
                $booking->getCreatedAt(),
                TimeUnit::hours(self::EXPIRATION_WORKING_HOURS),
            );

            if ($deadline > $command->referenceTime) {
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
