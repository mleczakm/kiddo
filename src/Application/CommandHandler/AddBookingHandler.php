<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\AddBooking;
use App\Application\UseCase\PlaceSingleReservation;

// Registered as a message handler via services.yaml (all classes directly
// under Application/CommandHandler/ are auto-tagged 'messenger.message_handler'),
// not the #[AsMessageHandler] attribute - do not add it here, it would
// double-register this handler.
final readonly class AddBookingHandler
{
    public function __construct(
        private PlaceSingleReservation $placeSingleReservation,
    ) {}

    public function __invoke(AddBooking $command): void
    {
        ($this->placeSingleReservation)($command);
    }
}
