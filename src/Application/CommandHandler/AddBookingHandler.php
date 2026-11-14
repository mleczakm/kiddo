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

    /**
     * @throws \App\Application\UseCase\PriceQuoteMismatchException
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Math\Exception\NumberFormatException
     * @throws \Brick\Math\Exception\RoundingNecessaryException
     * @throws \Brick\Money\Exception\UnknownCurrencyException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     * @throws \Doctrine\DBAL\Exception
     * @throws \LogicException
     * @throws \Random\RandomException
     */
    public function __invoke(AddBooking $command): void
    {
        ($this->placeSingleReservation)($command);
    }
}
