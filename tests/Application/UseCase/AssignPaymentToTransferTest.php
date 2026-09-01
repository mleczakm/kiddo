<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\AssignPaymentToTransfer;
use App\Entity\Payment;
use App\Entity\Transfer;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class AssignPaymentToTransferTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private AssignPaymentToTransfer $assignPaymentToTransfer;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var AssignPaymentToTransfer $assignPaymentToTransfer */
        $assignPaymentToTransfer = $container->get(AssignPaymentToTransfer::class);
        $this->assignPaymentToTransfer = $assignPaymentToTransfer;
    }

    /**
     * @return array{Payment, Transfer}
     */
    private function createPendingPaymentAndUnmatchedTransfer(string $paymentAmount, string $transferAmount): array
    {
        $user = UserAssembler::new()->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of($paymentAmount, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $transfer = TransferAssembler::new()->withAmount($transferAmount)->assemble();

        $this->em->persist($user);
        $this->em->persist($payment);
        $this->em->persist($transfer);
        $this->em->flush();

        return [$payment, $transfer];
    }

    public function testAssigningAnExactTransferLinksItAndMarksThePaymentPaid(): void
    {
        [$payment, $transfer] = $this->createPendingPaymentAndUnmatchedTransfer('100.00', '100.00');
        $paymentId = $payment->getId();
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        ($this->assignPaymentToTransfer)($transferId, $paymentId);

        $this->em->clear();

        $reloadedPayment = $this->em->find(Payment::class, $paymentId);
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_PAID, $reloadedPayment->getStatus());
        static::assertFalse($reloadedPayment->isFlaggedForReview());

        $reloadedTransfer = $this->em->find(Transfer::class, $transferId);
        static::assertInstanceOf(Transfer::class, $reloadedTransfer);
        static::assertTrue($paymentId->equals($reloadedTransfer->getPayment()?->getId() ?? new Ulid()));
    }

    public function testAssigningAnOverpayingTransferFlagsThePaymentForReview(): void
    {
        [$payment, $transfer] = $this->createPendingPaymentAndUnmatchedTransfer('100.00', '150.00');
        $paymentId = $payment->getId();
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        ($this->assignPaymentToTransfer)($transferId, $paymentId);

        $this->em->clear();

        $reloadedPayment = $this->em->find(Payment::class, $paymentId);
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_PAID, $reloadedPayment->getStatus());
        static::assertTrue($reloadedPayment->isFlaggedForReview());
    }

    public function testAssigningAnUnderpayingTransferLinksItButLeavesThePaymentPending(): void
    {
        [$payment, $transfer] = $this->createPendingPaymentAndUnmatchedTransfer('100.00', '40.00');
        $paymentId = $payment->getId();
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        ($this->assignPaymentToTransfer)($transferId, $paymentId);

        $this->em->clear();

        $reloadedPayment = $this->em->find(Payment::class, $paymentId);
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_PENDING, $reloadedPayment->getStatus());

        $reloadedTransfer = $this->em->find(Transfer::class, $transferId);
        static::assertInstanceOf(Transfer::class, $reloadedTransfer);
        static::assertTrue($paymentId->equals($reloadedTransfer->getPayment()?->getId() ?? new Ulid()));
    }

    public function testRejectsATransferThatIsAlreadyAssignedToAPayment(): void
    {
        [$payment, $transfer] = $this->createPendingPaymentAndUnmatchedTransfer('100.00', '100.00');
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        ($this->assignPaymentToTransfer)($transferId, $payment->getId());

        $otherUser = UserAssembler::new()->withEmail('other@example.com')->assemble();
        $otherPayment = PaymentAssembler::new()
            ->withUser($otherUser)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->em->persist($otherUser);
        $this->em->persist($otherPayment);
        $this->em->flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already assigned');

        ($this->assignPaymentToTransfer)($transferId, $otherPayment->getId());
    }

    public function testThrowsWhenTheTransferDoesNotExist(): void
    {
        [$payment] = $this->createPendingPaymentAndUnmatchedTransfer('100.00', '100.00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transfer 999999 not found');

        ($this->assignPaymentToTransfer)(999_999, $payment->getId());
    }

    public function testThrowsWhenThePaymentDoesNotExist(): void
    {
        [, $transfer] = $this->createPendingPaymentAndUnmatchedTransfer('100.00', '100.00');
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ($this->assignPaymentToTransfer)($transferId, new Ulid());
    }

    public function testThrowsWhenThePaymentIsNoLongerPending(): void
    {
        [$payment, $transfer] = $this->createPendingPaymentAndUnmatchedTransfer('100.00', '100.00');
        $payment->setStatus(Payment::STATUS_PAID);
        $this->em->flush();
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pending payment');

        ($this->assignPaymentToTransfer)($transferId, $payment->getId());
    }
}
