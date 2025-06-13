<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\AddBooking;
use App\Application\Command\SendReservationNotification;
use Brick\Money\Currency;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AddBookingHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus
    ) {}

    public function __invoke(AddBooking $command): void
    {

        $this->em->persist($command->booking);

        $this->bus->dispatch(
            new SendReservationNotification(
                $command->booking->getUser()
                    ->getEmail(),
                $command->booking->getUser()
                    ->getName(),
                $command->booking->getPayment()?->getPaymentCode()?->getCode() ?? '',
                $command->booking->getPayment()?->getAmount() ?? Money::zero(Currency::of('PL'))
            )
        );
    }
}
