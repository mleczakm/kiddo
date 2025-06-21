<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckExpiredPayments;
use App\Repository\PaymentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Entity\Payment;
use Symfony\Component\Workflow\Registry as WorkflowRegistry;

#[AsMessageHandler]
final readonly class CheckExpiredPaymentsHandler
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private WorkflowRegistry $workflowRegistry,
    ) {}

    public function __invoke(CheckExpiredPayments $command): void
    {
        $expiredPayments = $this->paymentRepository->findExpiredPendingPayments($command->expirationMinutes);

        foreach ($expiredPayments as $payment) {
            $workflow = $this->workflowRegistry->get($payment);
            if ($workflow->can($payment, Payment::TRANSITION_EXPIRE)) {
                $workflow->apply($payment, Payment::TRANSITION_EXPIRE);
            }
        }
    }
}
