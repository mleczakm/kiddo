<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\Workflow\BookingStateMachineInterface;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Message\ReactivateBooking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ReactivateBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingStateMachineInterface $bookingStateMachine,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ReactivateBooking $command): void
    {
        $booking = $this->bookingRepository->find($command->getBookingId());

        if (!$booking) {
            $this->logger->error('Booking not found for reactivation', [
                'bookingId' => $command->getBookingId(),
                'reactivatedById' => $command->getReactivatedBy()->getId(),
            ]);
            return;
        }

        if (!$this->bookingStateMachine->can($booking, 'reactivate')) {
            // Idempotent: the booking is already active (or otherwise cannot be
            // reactivated). This runs as a fire-and-forget message from the admin
            // UI, so a fatal here is just noise — log and stop.
            $this->logger->info('Skipping booking reactivate — transition not applicable', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'status' => $booking->getStatus(),
            ]);

            return;
        }

        $this->bookingStateMachine->apply($booking, 'reactivate');
        $booking->reactivate();

        $this->logger->info('Booking reactivated', [
            'bookingId' => $booking->getId()->toRfc4122(),
            'reactivatedById' => $command->getReactivatedBy()->getId(),
            'reason' => $command->getReason(),
        ]);
    }
}
