<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReactivateBooking;
use App\Repository\BookingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class ReactivateBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        #[Autowire(service: 'state_machine.booking')]
        private readonly WorkflowInterface $bookingStateMachine,
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
            $this->logger->error('Cannot apply reactivate transition to booking', [
                'bookingId' => $booking->getId()->toRfc4122(),
                'status' => $booking->getStatus(),
            ]);
            throw new \RuntimeException('Cannot reactivate this booking in its current state');
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
