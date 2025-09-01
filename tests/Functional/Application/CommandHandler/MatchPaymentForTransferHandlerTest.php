<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MatchPaymentForTransferHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    private EntityManagerInterface $entityManager;

    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->messageBus = $container->get(MessageBusInterface::class);
    }

    public function testMatchesPaymentWithSingleWordCode(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        // Set a specific code for testing - using reflection to override the generated code
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        $codeProperty->setValue($paymentCode, 'TEST');

        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('Payment for order TEST additional text')
            ->withAmount('100.00')
            ->withSender('John Doe')
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
        $codeProperty->setAccessible(true);
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

        $this->assertTrue($payment->getTransfers()->contains($transfer));
        $this->assertSame($payment, $transfer->getPayment());
        $this->assertEquals(Payment::STATUS_PAID, $payment->getStatus());
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
        $codeProperty->setAccessible(true);
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

        $this->assertTrue($payment->getTransfers()->contains($transfer));
        $this->assertSame($payment, $transfer->getPayment());
        $this->assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

    public function testMatchesFirstFoundCodeWhenMultipleCodesInTitle(): void
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
        $codeProperty->setAccessible(true);
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

    public function testCaseInsensitiveMatching(): void
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
        $codeProperty->setAccessible(true);
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
        $codeProperty->setAccessible(true);
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
        $this->assertTrue($payment->getTransfers()->contains($transfer));
        $this->assertSame($payment, $transfer->getPayment());

        // But status should remain the same since transition is not allowed
        $this->assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

    public function testDispatchesTransferNotMatchedCommandWhenNoCodeFound(): void
    {
        $transfer = TransferAssembler::new()
            ->withTitle('Transfer without any payment code')
            ->withAmount('100.00')
            ->withSender('No Code User')
            ->withAccountNumber('1234567890')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($transfer);

        // Transfer should not be associated with any payment
        $this->assertNull($transfer->getPayment());

        // Check that TransferNotMatchedCommand was dispatched
        $this->bus()
            ->dispatched()
            ->assertContains(TransferNotMatchedCommand::class);
    }

    public function testDispatchesTransferNotMatchedCommandWhenCodeNotFound(): void
    {
        $transfer = TransferAssembler::new()
            ->withTitle('Transfer with NONEXISTENT code')
            ->withAmount('100.00')
            ->withSender('Nonexistent Code')
            ->withAccountNumber('1234567890')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($transfer);

        // Transfer should not be associated with any payment
        $this->assertNull($transfer->getPayment());

        // Check that TransferNotMatchedCommand was dispatched
        $this->bus()
            ->dispatched()
            ->assertContains(TransferNotMatchedCommand::class);
    }

    public function testHandlesEmptyTitle(): void
    {
        $transfer = TransferAssembler::new()
            ->withTitle('')
            ->withAmount('100.00')
            ->withSender('Empty Title')
            ->withAccountNumber('1234567890')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($transfer);

        // Transfer should not be associated with any payment
        $this->assertNull($transfer->getPayment());

        // Check that TransferNotMatchedCommand was dispatched
        $this->bus()
            ->dispatched()
            ->assertContains(TransferNotMatchedCommand::class);
    }

    public function testHandlesTitleWithOnlySpaces(): void
    {
        $transfer = TransferAssembler::new()
            ->withTitle('   ')
            ->withAmount('100.00')
            ->withSender('Spaces Only')
            ->withAccountNumber('1234567890')
            ->assemble();
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Act
        $command = new MatchPaymentForTransfer($transfer);
        $this->messageBus->dispatch($command);

        // Assert
        $this->entityManager->refresh($transfer);

        // Transfer should not be associated with any payment
        $this->assertNull($transfer->getPayment());

        // Check that TransferNotMatchedCommand was dispatched
        $this->bus()
            ->dispatched()
            ->assertContains(TransferNotMatchedCommand::class);
    }

    public function testMatchesPaymentCodeWithSpecialCharactersInTitle(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('400.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        $codeProperty->setValue($paymentCode, 'SPEC');

        $this->entityManager->persist($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('Payment: SPEC - for services!')
            ->withAmount('400.00')
            ->withSender('Special Chars')
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
}
