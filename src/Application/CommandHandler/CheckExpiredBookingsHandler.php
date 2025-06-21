<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckExpiredBookings;
use App\Repository\BookingRepository;
use Symfony\Component\Workflow\WorkflowInterface;

class CheckExpiredBookingsHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly WorkflowInterface $bookingStateMachine
    ) {}

    public function __invoke(CheckExpiredBookings $command): void
    {
        $expiredBookings = $this->bookingRepository->findExpiredPendingBookings($command->expirationTime);

        foreach ($expiredBookings as $booking) {
            if ($this->bookingStateMachine->can($booking, 'cancel')) {
                $this->bookingStateMachine->apply($booking, 'cancel');
            }
        }
    }
}
