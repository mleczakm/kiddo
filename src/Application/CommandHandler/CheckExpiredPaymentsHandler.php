<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckExpiredPayments;
use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\Payment;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckExpiredPaymentsHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentStateMachineInterface $paymentStateMachine,
    ) {}

    public function __invoke(CheckExpiredPayments $command): void
    {
        $cutoff = $command->referenceTime->modify(sprintf('-%d minutes', Payment::VALIDITY_MINUTES));

        foreach ($this->paymentRepository->findExpiredPendingPayments($cutoff) as $payment) {
            if ($this->paymentStateMachine->can($payment, Payment::TRANSITION_EXPIRE)) {
                $this->paymentStateMachine->apply($payment, Payment::TRANSITION_EXPIRE);
            }
        }
    }
}
