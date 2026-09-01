<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Repository\TransferRepositoryInterface;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Canonical use case for an admin manually attaching an unmatched bank
 * transfer to a pending payment. Extracted from AssignPaymentModalComponent,
 * which previously fetched both entities and applied the payment workflow
 * transition inline - keeping it in the component meant the reference to the
 * transfer had to survive Live Component re-hydration, and a transfer removed
 * in the meantime (rejected elsewhere, assigned in another tab) blew up with a
 * TypeError before any guard could run. Here the identifiers are resolved
 * fresh on every call, so a vanished transfer is a plain domain error.
 *
 * Mirrors MatchPaymentForTransferHandler: link via Payment::addTransfer,
 * flag for review on overpayment, and only apply `pay` when the workflow
 * guard allows it (exact/over payment, or an admin acting in-request).
 */
final readonly class AssignPaymentToTransfer
{
    public function __construct(
        private TransferRepositoryInterface $transferRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentStateMachineInterface $paymentStateMachine,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @throws \InvalidArgumentException                     when the transfer or the payment no longer exists
     * @throws \RuntimeException                             when the transfer is already assigned, or the payment is not pending
     * @throws \Brick\Money\Exception\MoneyMismatchException when the transfer and payment currencies diverge
     * @throws \Brick\Math\Exception\MathException           when a transfer amount cannot be parsed into money
     */
    public function __invoke(int $transferId, Ulid $paymentId): void
    {
        $transfer = $this->transferRepository->find($transferId);
        if ($transfer === null) {
            throw new \InvalidArgumentException(sprintf('Transfer %d not found', $transferId));
        }

        if ($transfer->getPayment() !== null) {
            throw new \RuntimeException('This transfer is already assigned to a payment.');
        }

        $payment = $this->paymentRepository->find($paymentId);
        if ($payment === null) {
            throw new \InvalidArgumentException(sprintf('Payment %s not found', $paymentId));
        }

        if ($payment->getStatus() !== Payment::STATUS_PENDING) {
            throw new \RuntimeException('Only a pending payment can take a manually assigned transfer.');
        }

        $payment->addTransfer($transfer);

        if ($this->paymentStateMachine->can($payment, Payment::TRANSITION_PAY)) {
            if ($payment->getAmountPaid()->isGreaterThan($payment->getAmount())) {
                $payment->flagForReview();
            }

            $this->paymentStateMachine->apply($payment, Payment::TRANSITION_PAY);
        }

        $this->em->flush();
    }
}
