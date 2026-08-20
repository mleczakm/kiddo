<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
class MatchPaymentForTransferHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    private EntityManagerInterface $entityManager;

    private MessageBusInterface $messageBus;

    /**
     * @return array<string, array{string, string}>
     */
    public static function paymentMatchingProvider(): array
    {
        return [
            'single word code' => ['TEST', 'Payment for order TEST additional text'],
            'missed zero with O' => ['TES0', 'Payment for order TESO additional text'],
            'missed O with zero' => ['TESO', 'Payment for order TES0 additional text'],
            'missed O with zero and zero with O' => ['T0SO', 'Payment for order TOS0 additional text'],
            'code in middle of title' => ['XYZ9', 'Some random text XYZ9 more text here'],
            'code at end of title' => ['END1', 'Transfer description ending with END1'],
            'special characters in title' => ['SPEC', 'Payment: SPEC - for services!'],
            'special character after code' => ['SPEC', 'Payment: SPEC, - for services!'],
            'missed code by space' => ['SPEC', 'Payment: SPE C, - for services!'],
            'missed code by space 2nd' => ['SPEC', 'Pay ment: SPE C, - for services!'],
            'missed code by multiple spaces' => ['SPEC', 'Pay  ment: SPE  C, - for services!'],
            'missed code by multiple spaces 2nd' => ['SPEC', 'Pay  ment: SPE   C, - for services!'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTitleProvider(): array
    {
        return [
            'empty title' => [''],
            'title with only spaces' => ['   '],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function noMatchingCodeProvider(): array
    {
        return [
            'no code in title' => ['Transfer without any payment code'],
            'non-existent code' => ['Transfer with NONEXISTENT code'],
        ];
    }

    #[Test]
    #[DataProvider('paymentMatchingProvider')]
    public function matchesPaymentCodeInTitle(string $code, string $title): void
    {
        $amount = 100.00;
        $currency = 'PLN';
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of((string) $amount, $currency))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, $code);
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle($title)
            ->withAmount((string) $amount)
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($transfer);

        $this->assertTrue($payment->getTransfers()->contains($transfer));
        $this->assertSame($payment, $transfer->getPayment());
        $this->assertEquals(Payment::STATUS_PAID, $payment->getStatus());
        $this->assertNotNull($payment->getPaidAt());
    }

    public function testMatchesPaymentWithCodeInMiddleOfTitle(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('200.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'XYZ9');

        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('Some random text XYZ9 more text here')
            ->withAmount('200.00')
            ->withSender('Jane Smith')
            ->withAccountNumber('0987654321')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($transfer);

        static::assertTrue($payment->getTransfers()->contains($transfer));
        static::assertSame($payment, $transfer->getPayment());
        static::assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

    public function testMatchesPaymentWithCodeAtEndOfTitle(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('150.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'END1');

        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('Transfer description ending with END1')
            ->withAmount('150.00')
            ->withSender('Bob Johnson')
            ->withAccountNumber('1111222233')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($transfer);

        static::assertTrue($payment->getTransfers()->contains($transfer));
        static::assertSame($payment, $transfer->getPayment());
        static::assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

    #[Test]
    public function matchesFirstFoundCodeWhenMultipleCodesInTitle(): void
    {
        // Arrange
        $user1 = UserAssembler::new()->assemble();
        $user2 = UserAssembler::new()->assemble();
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);

        $payment1 = PaymentAssembler::new()
            ->withUser($user1)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment1);

        $payment2 = PaymentAssembler::new()
            ->withUser($user2)
            ->withAmount(Money::of('200.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment2);

        $paymentCode1 = new PaymentCode($payment1);
        $reflection = new \ReflectionClass($paymentCode1);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode1, 'AAA1');
        $this->entityManager->persist($paymentCode1);

        $paymentCode2 = new PaymentCode($payment2);
        $codeProperty->setValue($paymentCode2, 'BBB2');
        $this->entityManager->persist($paymentCode2);

        $transfer = TransferAssembler::new()
            ->withTitle('Payment AAA1 and also BBB2')
            ->withAmount('100.00')
            ->withSender('Multi Code')
            ->withAccountNumber('9999888877')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($payment1);
        $this->entityManager->refresh($payment2);
        $this->entityManager->refresh($transfer);

        // Should match the first payment (AAA1) because it appears first in the title
        $this->assertTrue($payment1->getTransfers()->contains($transfer));
        $this->assertSame($payment1, $transfer->getPayment());
        $this->assertEquals(Payment::STATUS_PAID, $payment1->getStatus());

        // Second payment should remain unchanged
        $this->assertFalse($payment2->getTransfers()->contains($transfer));
        $this->assertEquals(Payment::STATUS_PENDING, $payment2->getStatus());
    }

    #[Test]
    public function matchesPaymentCodeCaseInsensitively(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('300.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'CASE');
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('payment with lowercase case code')
            ->withAmount('300.00')
            ->withSender('Case Test')
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

        $this->assertTrue($payment->getTransfers()->contains($transfer));
        $this->assertSame($payment, $transfer->getPayment());
        $this->assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

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
    public function matchesRealCodePrecedingBlikPhoneToPhoneBoilerplate(): void
    {
        // Arrange: BLIK phone-to-phone transfers carry a bank-generated
        // "Od: <phone> Do: <phone>" suffix, but the customer-entered payment
        // code preceding it is still real and must still be matched.
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('60.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($paymentCode, 'ZW4D');
        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('ZW4D                                Od: 48512112450 Do: 485*****213')
            ->withAmount('60,00')
            ->withSender('ANNA ROSIŃSKA')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($payment);
        $this->entityManager->refresh($transfer);

        $this->assertTrue($payment->getTransfers()->contains($transfer));
        $this->assertSame($payment, $transfer->getPayment());
        $this->assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

    #[Test]
    #[DataProvider('noMatchingCodeProvider')]
    public function dispatchesTransferNotMatchedWhenNoMatchingCodeFound(string $title): void
    {
        $amount = 100.00;
        $currency = 'PLN';
        $sender = 'Test Sender';
        $accountNumber = '1234567890';
        // Arrange
        $transfer = TransferAssembler::new()
            ->withTitle($title)
            ->withAmount((string) $amount)
            ->withSender($sender)
            ->withAccountNumber($accountNumber)
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->setupPendingPayment();
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($transfer);
        $this->assertNull($transfer->getPayment());
        $this->bus()->dispatched()->assertContains(TransferNotMatchedCommand::class);
    }

    #[Test]
    #[DataProvider('invalidTitleProvider')]
    public function handlesInvalidTitles(string $title): void
    {
        $amount = 100.00;
        $currency = 'PLN';
        $sender = 'Test Sender';
        $accountNumber = '1234567890';
        $transfer = TransferAssembler::new()
            ->withTitle($title)
            ->withAmount((string) $amount)
            ->withSender($sender)
            ->withAccountNumber($accountNumber)
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->setupPendingPayment();
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($transfer);
        $this->assertNull($transfer->getPayment());
        $this->bus()->dispatched()->assertContains(TransferNotMatchedCommand::class);
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

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->messageBus = $container->get(MessageBusInterface::class);
    }

    private function setupPendingPayment(): void
    {
        $user = UserAssembler::new()->withName('Test User')->withEmail('test@example.com')->assemble();
        $this->entityManager->persist($user);

        $paymentCode = PaymentCodeAssembler::new()->withCode('1234')->assemble();
        $this->entityManager->persist($paymentCode);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withPaymentCode($paymentCode)
            ->withAmount(Money::of(150, 'PLN')) // 150.00 PLN
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);
    }
}
