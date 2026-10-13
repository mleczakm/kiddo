<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\CommandHandler\MatchPaymentForTransferHandler;
use App\Application\Repository\PaymentCodeRepositoryInterface;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\PaymentCode;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('unit')]
final class BatchPaymentMatchingTest extends TestCase
{
    public function testBatchLookupKeepsTitlePriorityWhenDatabaseReturnsReverseOrder(): void
    {
        $early = PaymentAssembler::new()->assemble();
        $late = PaymentAssembler::new()->assemble();
        $codes = $this->createMock(PaymentCodeRepositoryInterface::class);
        $codes->expects(static::never())->method('findOneByCode');
        $codes
            ->expects(static::once())
            ->method('findBy')
            ->willReturn([
                new PaymentCode($late, 'LATE'),
                new PaymentCode($early, 'EARL'),
            ]);
        $workflow = $this->createMock(PaymentStateMachineInterface::class);
        $workflow->method('can')->willReturn(false);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::never())->method('dispatch');
        $handler = new MatchPaymentForTransferHandler($codes, $workflow, $bus);
        $transfer = TransferAssembler::new()->withTitle('EARL LATE')->assemble();

        $handler(new MatchPaymentForTransfer($transfer));

        static::assertSame($early, $transfer->getPayment());
        static::assertCount(0, $late->getTransfers());
    }
}
