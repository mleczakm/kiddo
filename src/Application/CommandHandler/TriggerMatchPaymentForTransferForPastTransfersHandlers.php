<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\TriggerMatchPaymentForTransferForPastTransfers;
use App\Entity\Transfer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TriggerMatchPaymentForTransferForPastTransfersHandlers
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus
    ) {}

    public function __invoke(TriggerMatchPaymentForTransferForPastTransfers $command): void
    {
        /** @var Transfer $transfer */
        foreach ($this->entityManager->createQuery('
            SELECT t
            FROM App\Entity\Transfer t
            WHERE t.payment IS NULL
        ')->getArrayResult() as $transfer) {
            $this->bus->dispatch(new MatchPaymentForTransfer($transfer));
        }
    }
}
