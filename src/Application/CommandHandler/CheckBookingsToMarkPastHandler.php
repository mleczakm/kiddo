<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckBookingsToMarkPast;
use App\Application\Repository\BookingRepositoryInterface;
use App\Application\Workflow\BookingStateMachineInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckBookingsToMarkPastHandler
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private BookingStateMachineInterface $bookingStateMachine,
    ) {}

    public function __invoke(CheckBookingsToMarkPast $_command): void
    {
        $activeBookings = $this->bookingRepository->findActiveBookings();

        foreach ($activeBookings as $booking) {
            if (!$booking->shouldBeMarkedAsPast()) {
                continue;
            }

            if ($this->bookingStateMachine->can($booking, 'complete')) {
                $this->bookingStateMachine->apply($booking, 'complete');
            }
        }
    }
}
