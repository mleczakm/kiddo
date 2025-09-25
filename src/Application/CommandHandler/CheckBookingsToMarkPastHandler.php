<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckBookingsToMarkPast;
use App\Repository\BookingRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry as WorkflowRegistry;

#[AsMessageHandler]
final readonly class CheckBookingsToMarkPastHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private WorkflowRegistry $workflowRegistry,
    ) {}

    public function __invoke(CheckBookingsToMarkPast $command): void
    {
        $activeBookings = $this->bookingRepository->findActiveBookings();

        foreach ($activeBookings as $booking) {
            if (! $booking->shouldBeMarkedAsPast()) {
                continue;
            }

            $workflow = $this->workflowRegistry->get($booking);
            if ($workflow->can($booking, 'complete')) {
                $workflow->apply($booking, 'complete');
            }
        }
    }
}
