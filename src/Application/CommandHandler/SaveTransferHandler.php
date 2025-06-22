<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\SaveTransfer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SaveTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $commandBus,
    ) {}

    public function __invoke(SaveTransfer $command): void
    {
        $this->entityManager->persist($command->transfer);

        $this->commandBus->dispatch(new MatchPaymentForTransfer($command->transfer));
    }
}
