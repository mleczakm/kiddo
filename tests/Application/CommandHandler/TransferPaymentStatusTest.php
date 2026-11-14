<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class TransferPaymentStatusTest extends MatchPaymentForTransferHandlerTestCase
{
    public function testMatchingPaymentWritesAnActivityLogEntry(): void
    {
        $user = UserAssembler::new()->withName('Marta Kowalczyk')->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'ACTV');
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()->withTitle('Payment for order ACTV')->withAmount('100.00')->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        $activityLogs = $this->entityManager
            ->getRepository(ActivityLog::class)
            ->findBy([
                'type' => ActivityType::PAYMENT_RECEIVED,
            ]);

        static::assertCount(1, $activityLogs);
        static::assertSame($user, $activityLogs[0]->getSubject());
        static::assertStringContainsString('Marta Kowalczyk', $activityLogs[0]->getTitle());
    }

    public function testDoesNotTransitionPaymentIfCannotPay(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PAID) // Already paid, cannot transition to pay again
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'PAID');

        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('Transfer with PAID code')
            ->withAmount('100.00')
            ->withSender('Already Paid')
            ->withAccountNumber('1234567890')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($transfer);

        // Transfer should still be associated with payment
        static::assertTrue($payment->getTransfers()->contains($transfer));
        static::assertSame($payment, $transfer->getPayment());

        // But status should remain the same since transition is not allowed
        static::assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

    #[Test]
    public function underpaymentLeavesPaymentPending(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'UNDR');
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()->withTitle('Payment UNDR partial')->withAmount('40.00')->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($transfer);

        // The transfer is still attached to the payment (it counts toward the
        // cumulative total), but a single 40/100 transfer isn't enough to pay.
        static::assertTrue($payment->getTransfers()->contains($transfer));
        static::assertSame($payment, $transfer->getPayment());
        static::assertSame(Payment::STATUS_PENDING, $payment->getStatus());
        static::assertNull($payment->getPaidAt());
    }

    #[Test]
    public function cumulativeTransfersEventuallyPayInFull(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'CUML');
        $this->entityManager->persist($paymentCode);

        $firstTransfer = TransferAssembler::new()->withTitle('CUML part one')->withAmount('40.00')->assemble();
        $this->entityManager->persist($firstTransfer);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($firstTransfer));
        $this->entityManager->refresh($payment);
        static::assertSame(Payment::STATUS_PENDING, $payment->getStatus(), 'First partial transfer alone must not pay');

        $secondTransfer = TransferAssembler::new()->withTitle('CUML part two')->withAmount('60.00')->assemble();
        $this->entityManager->persist($secondTransfer);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($secondTransfer));
        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($firstTransfer);
        $this->entityManager->refresh($secondTransfer);

        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
        static::assertNotNull($payment->getPaidAt());
        static::assertTrue($payment->getTransfers()->contains($firstTransfer));
        static::assertTrue($payment->getTransfers()->contains($secondTransfer));
    }

    #[Test]
    public function overpaymentIsAcceptedAsPaid(): void
    {
        // Superseded by overpaymentFlagsThePaymentForReview() below (Stage 6
        // hardening added the admin-review marker); kept for the status/paidAt
        // coverage.
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'OVER');
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()->withTitle('OVER paid too much')->withAmount('150.00')->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($transfer);

        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
        static::assertNotNull($payment->getPaidAt());
        static::assertTrue($payment->getTransfers()->contains($transfer));
    }

    #[Test]
    public function overpaymentFlagsThePaymentForReview(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'REVW');
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()->withTitle('REVW paid too much')->withAmount('150.00')->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $this->entityManager->refresh($payment);

        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
        static::assertTrue($payment->isFlaggedForReview());
    }

    #[Test]
    public function exactPaymentDoesNotFlagForReview(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'EXCT');
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()->withTitle('EXCT exact amount')->withAmount('100.00')->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $this->entityManager->refresh($payment);

        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
        static::assertFalse($payment->isFlaggedForReview());
    }

    #[Test]
    public function aTransferAlreadyAttachedToAPaymentIsNotReprocessed(): void
    {
        // Idempotency guard (Stage 6): re-running the matching handler
        // against an already-resolved transfer (e.g. the past-transfers
        // rematch sweep) must not re-run title matching at all.
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $this->entityManager->persist($payment);

        $otherPayment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($otherPayment);

        $otherCode = new PaymentCode($otherPayment);
        $reflection = new \ReflectionClass($otherCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($otherCode, 'IDEM');
        $this->entityManager->persist($otherCode);

        // The transfer's title contains a real code for $otherPayment, but
        // it's already attached to $payment - matching must not touch it.
        $transfer = TransferAssembler::new()
            ->withTitle('IDEM already resolved')
            ->withAmount('100.00')
            ->withPayment($payment)
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $this->entityManager->refresh($otherPayment);
        $this->entityManager->refresh($transfer);

        static::assertSame($payment, $transfer->getPayment());
        static::assertSame(Payment::STATUS_PENDING, $otherPayment->getStatus());
        static::assertFalse($otherPayment->getTransfers()->contains($transfer));
    }
}
