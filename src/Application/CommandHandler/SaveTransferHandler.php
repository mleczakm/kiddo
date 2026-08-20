<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferRequiresReviewCommand;
use App\Application\Command\SaveTransfer;
use App\Application\Service\Payment\TransferReviewThresholdProvider;
use App\Application\Service\TransferMoneyParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class SaveTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $commandBus,
        private TransferReviewThresholdProvider $reviewThreshold,
    ) {}

    public function __invoke(SaveTransfer $command): void
    {
        // Every transfer is persisted, regardless of amount - it must never
        // silently vanish (Stage 6 hardening). One above the configurable
        // review threshold skips automatic matching and is routed to admins
        // for manual review/assignment instead.
        $this->entityManager->persist($command->transfer);

        $amount = TransferMoneyParser::transferMoneyStringToMoneyObject($command->transfer->amount);

        $followUpCommand = $amount->isGreaterThan($this->reviewThreshold->get())
            ? new TransferRequiresReviewCommand($command->transfer)
            : new MatchPaymentForTransfer($command->transfer);

        $this->commandBus->dispatch(new Envelope($followUpCommand)->with(new DispatchAfterCurrentBusStamp()));
    }
}
