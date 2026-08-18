<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckExpiredBookings;
use App\Application\Service\ActivityLogger;
use App\Entity\ActivityType;
use App\Repository\BookingRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class CheckExpiredBookingsHandler
{
    private const string CANCELLATION_REASON = 'Rezerwacja anulowana automatycznie — brak płatności w wymaganym terminie';

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly WorkflowInterface $bookingStateMachine,
        private readonly ActivityLogger $activityLogger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(CheckExpiredBookings $command): void
    {
        $expiredBookings = $this->bookingRepository->findExpiredPendingBookings($command->expirationTime);

        foreach ($expiredBookings as $booking) {
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
