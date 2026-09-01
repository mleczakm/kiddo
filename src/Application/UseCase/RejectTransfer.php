<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Repository\TransferRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Canonical use case for an admin rejecting an unmatched bank transfer.
 * Extracted from AdminPaymentComponent and PaymentComponent, which carried
 * an identical inline `find + remove + flush`. Transfer is SoftDeleteable
 * with hardDelete: true, so a reject genuinely drops the row - guarding
 * against rejecting an already-assigned transfer keeps that from silently
 * corrupting a payment's paid amount.
 *
 * Missing transfer is a no-op: a double-click or a stale button on a list
 * that already moved on must not raise.
 */
final readonly class RejectTransfer
{
    public function __construct(
        private TransferRepositoryInterface $transferRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @throws \RuntimeException when the transfer is already assigned to a payment
     */
    public function __invoke(int $transferId): void
    {
        $transfer = $this->transferRepository->find($transferId);
        if ($transfer === null) {
            return;
        }

        if ($transfer->getPayment() !== null) {
            throw new \RuntimeException('Cannot reject a transfer that is already assigned to a payment.');
        }

        $this->em->remove($transfer);
        $this->em->flush();
    }
}
