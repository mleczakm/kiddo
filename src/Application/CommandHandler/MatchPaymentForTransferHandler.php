<?php

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Entity\PaymentCode;
use Doctrine\ORM\EntityManagerInterface;


final class MatchPaymentForTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(MatchPaymentForTransfer $command): void
    {
        $transfer = $command->transfer;
        $title = $command->transfer->title;

        // Extract payment code from the transfer title (assuming format like "Payment ABC123")
        if (preg_match('/\b([A-Z0-9]{4,8})\b/', $title, $matches)) {
            $code = $matches[1];

            // Find payment code in the database
            $paymentCode = $this->entityManager->getRepository(PaymentCode::class)
                ->findOneBy(['code' => $code]);

            if ($paymentCode) {
                $payment = $paymentCode->getPayment();

                $paymentAmount = $payment->getAmount();
                $transferAmount = $transfer->getAmount();
                if ($paymentAmount->isEqualTo($transferAmount)) {
                    $payment->markAsPaid();
                    $this->entityManager->flush();
                }

                // Or simply mark as paid if code matches
                $payment->markAsPaid();
                $this->entityManager->flush();
            }
        }
    }
}