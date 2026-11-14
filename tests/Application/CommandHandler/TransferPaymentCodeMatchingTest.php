<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class TransferPaymentCodeMatchingTest extends MatchPaymentForTransferHandlerTestCase
{
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
}
