<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\SaveTransfer;
use App\Application\Service\TransferMoneyParser;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class SaveTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $commandBus,
    ) {}

    public function __invoke(SaveTransfer $command): void
    {
        if (TransferMoneyParser::transferMoneyStringToMoneyObject($command->transfer->amount)->isGreaterThan(
            Money::of(340, 'PLN')
        )) {
            return;
        }

        $this->entityManager->persist($command->transfer);

        $this->commandBus->dispatch(
            new Envelope(new MatchPaymentForTransfer($command->transfer))
                ->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
