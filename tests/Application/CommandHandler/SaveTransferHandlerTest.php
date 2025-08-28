<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\SaveTransfer;
use App\Tests\Assembler\TransferAssembler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class SaveTransferHandlerTest extends KernelTestCase
{
    public function testAmountsHigherThanExpectedAreGettingIgnored(): void
    {
        $transfer = TransferAssembler::new()->withAmount('341')->assemble();
        self::bootKernel();

        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        $messageBus->dispatch(new SaveTransfer($transfer));

        self::assertNull($transfer->getId());
    }

    public function testExpectedAmountsAreGettingSaved(): void
    {
        $transfer = TransferAssembler::new()->withAmount('340')->assemble();
        self::bootKernel();


        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        $messageBus->dispatch(new SaveTransfer($transfer));

        self::assertIsNumeric($transfer->getId());
    }
}
