<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\RejectTransfer;
use App\Entity\Payment;
use App\Entity\Transfer;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class RejectTransferTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private RejectTransfer $rejectTransfer;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var RejectTransfer $rejectTransfer */
        $rejectTransfer = $container->get(RejectTransfer::class);
        $this->rejectTransfer = $rejectTransfer;
    }

    public function testRejectingAnUnmatchedTransferRemovesIt(): void
    {
        $transfer = TransferAssembler::new()->assemble();
        $this->em->persist($transfer);
        $this->em->flush();
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        ($this->rejectTransfer)($transferId);

        $this->em->clear();

        static::assertNull($this->em->find(Transfer::class, $transferId));
    }

    public function testRejectingAnAlreadyRemovedTransferIsANoOp(): void
    {
        // 999999 never existed - a double-clicked reject button must not raise.
        ($this->rejectTransfer)(999_999);

        $this->expectNotToPerformAssertions();
    }

    public function testRefusesToRejectATransferThatIsAssignedToAPayment(): void
    {
        $user = UserAssembler::new()->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $transfer = TransferAssembler::new()->withAmount('100.00')->assemble();
        $payment->addTransfer($transfer);

        $this->em->persist($user);
        $this->em->persist($payment);
        $this->em->persist($transfer);
        $this->em->flush();
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        try {
            ($this->rejectTransfer)($transferId);
            static::fail('Expected a RuntimeException for an assigned transfer');
        } catch (\RuntimeException $exception) {
            static::assertStringContainsString('already assigned', $exception->getMessage());
        }

        $this->em->clear();
        static::assertInstanceOf(Transfer::class, $this->em->find(Transfer::class, $transferId));
    }
}
