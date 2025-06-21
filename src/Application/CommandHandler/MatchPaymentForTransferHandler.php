<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Entity\PaymentCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class MatchPaymentForTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkflowInterface $paymentStateMachine
    ) {}

    public function __invoke(MatchPaymentForTransfer $command): void
    {
        $transfer = $command->transfer;
        $title = $command->transfer->title;

        // Extract payment code from the transfer title (assuming format like "Payment ABC123")
        if (preg_match('/\b([A-Z0-9]{4,8})\b/', $title, $matches)) {
            $code = $matches[1];

            // Find payment code in the database
            $paymentCode = $this->entityManager->getRepository(PaymentCode::class)
                ->findOneBy([
                    'code' => $code,
                ]);

            if ($paymentCode) {
                $payment = $paymentCode->getPayment();
                $payment->addTransfer($transfer);

                if ($this->paymentStateMachine->can($payment, 'pay')) {
                    $this->paymentStateMachine->apply($payment, 'pay');
                }
            }
        }
    }
}
