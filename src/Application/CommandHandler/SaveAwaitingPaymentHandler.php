<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SaveAwaitingPayment;
use App\Application\Command\SendReservationNotification;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class SaveAwaitingPaymentHandler
{
    public function __construct(private EntityManagerInterface $em, private MessageBusInterface $bus)
    {
    }

    public function __invoke(SaveAwaitingPayment $command): void
    {
        $reservation = new Reservation($command->user, $command->lesson);
        $command->awaitingPayment->addReservation($reservation);
        $this->em->persist($command->awaitingPayment);
        $this->em->persist($reservation);

        $this->bus->dispatch(new SendReservationNotification($command->user->getEmail(), $command->user->getName(), $command->awaitingPayment->getCode(), $command->awaitingPayment->getAmount()));
    }
}

