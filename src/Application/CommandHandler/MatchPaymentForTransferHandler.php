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

        foreach (explode(' ', $title) as $word) {
            $paymentCode = $this->entityManager->getRepository(PaymentCode::class)
                ->findOneBy([
                    'code' => $word,
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
