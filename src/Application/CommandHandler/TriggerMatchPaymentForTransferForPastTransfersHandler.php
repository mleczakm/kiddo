<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\TriggerMatchPaymentForTransferForPastTransfers;
use App\Entity\Transfer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class TriggerMatchPaymentForTransferForPastTransfersHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus
    ) {}

    public function __invoke(TriggerMatchPaymentForTransferForPastTransfers $command): void
    {
        $transfers = $this->entityManager->createQuery('
            SELECT t
            FROM App\Entity\Transfer t
            WHERE t.payment IS NULL
            AND t.transferredAt > :date
        ')
            ->setParameter('date', Clock::get()->now()->sub(new \DateInterval('PT12H')))
            ->getResult();

        /** @var Transfer $transfer */
        foreach (is_array($transfers) ? $transfers : [] as $transfer) {
            $this->bus->dispatch(new MatchPaymentForTransfer($transfer));
        }
    }
}
