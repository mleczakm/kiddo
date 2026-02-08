<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\CommandHandler;

use PHPUnit\Framework\Attributes\Group;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\Payment;
use App\Application\Command\TriggerMatchPaymentForTransferForPastTransfers;
use App\Application\CommandHandler\TriggerMatchPaymentForTransferForPastTransfersHandler;
use App\Tests\Assembler\UserAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use Brick\Money\Money;

#[Group('functional')]
class TriggerMatchPaymentForTransferForPastTransfersHandlerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public function testHandlerDispatchesMatchPaymentForEligibleTransfers(): void
    {
        // Arrange: create user, payment, and transfer older than 12 hours using assemblers
        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(100, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = PaymentCodeAssembler::new()
            ->withCode('ABCD')
            ->withPayment($payment)
            ->assemble();
        $this->entityManager->persist($paymentCode);
        $payment->setPaymentCode($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('Payment for test ABCD')
            ->withAmount('100.00')
            ->withSender('Test Sender')
            ->withAccountNumber('1234567890')
            ->withTransferredAt(new \DateTimeImmutable('-1 hours'))
            ->assemble();
        $this->entityManager->persist($transfer);

        $this->entityManager->flush();

        // Act: run handler
        $handler = self::getContainer()->get(TriggerMatchPaymentForTransferForPastTransfersHandler::class);
        $handler(new TriggerMatchPaymentForTransferForPastTransfers());

        // Assert: transfer should be matched to payment (if logic allows)
        $this->entityManager->refresh($transfer);
        $this->entityManager->refresh($payment);
        $this->assertSame($payment, $transfer->getPayment());
    }

    public function testHandlerIgnoresTransfersOlderThanThreshold(): void
    {
        // Arrange: create user, payment, and transfer older than threshold (e.g. 2 days)
        $user = UserAssembler::new()
            ->withEmail('olduser@example.com')
            ->withName('Old User')
            ->assemble();
        $this->entityManager->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(100, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($payment);

        $paymentCode = PaymentCodeAssembler::new()
            ->withCode('OLDC')
            ->withPayment($payment)
            ->assemble();
        $this->entityManager->persist($paymentCode);
        $payment->setPaymentCode($paymentCode);

        $transfer = TransferAssembler::new()
            ->withTitle('Payment for test OLDC')
            ->withAmount('100.00')
            ->withSender('Old Sender')
            ->withAccountNumber('1234567890')
            ->withTransferredAt(new \DateTimeImmutable('-2 days'))
            ->assemble();
        $this->entityManager->persist($transfer);

        $this->entityManager->flush();

        // Act: run handler
        $handler = self::getContainer()->get(TriggerMatchPaymentForTransferForPastTransfersHandler::class);
        $handler(new TriggerMatchPaymentForTransferForPastTransfers());

        // Assert: transfer should NOT be matched to payment (older than threshold)
        $this->entityManager->refresh($transfer);
        $this->entityManager->refresh($payment);
        $this->assertNull($transfer->getPayment());
    }
}
