<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferRequiresReviewCommand;
use App\Application\Command\SaveTransfer;
use App\Tests\Assembler\TransferAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Stage 6 hardening: transfers are never silently discarded regardless of
 * amount. One above the (configurable, default 1000 PLN) review threshold is
 * still persisted, but is routed to admin review instead of automatic
 * matching - see TransferReviewThresholdProvider and MatchPaymentForTransferHandler.
 */
#[Group('functional')]
class SaveTransferHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    public function testAmountsAboveTheReviewThresholdAreStillPersistedButRoutedToReviewInsteadOfMatching(): void
    {
        $transfer = TransferAssembler::new()->withAmount('1500')->assemble();
        self::bootKernel();

        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        $messageBus->dispatch(new SaveTransfer($transfer));

        static::assertIsNumeric($transfer->getId());
        $this->bus()->dispatched()->assertContains(TransferRequiresReviewCommand::class);
        $this->bus()->dispatched()->assertNotContains(MatchPaymentForTransfer::class);
    }

    public function testAmountsAtOrBelowTheReviewThresholdAreSavedAndMatchedNormally(): void
    {
        $transfer = TransferAssembler::new()->withAmount('1000')->assemble();
        self::bootKernel();

        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        $messageBus->dispatch(new SaveTransfer($transfer));

        static::assertIsNumeric($transfer->getId());
        $this->bus()->dispatched()->assertContains(MatchPaymentForTransfer::class);
        $this->bus()->dispatched()->assertNotContains(TransferRequiresReviewCommand::class);
    }

    public function testExpectedAmountsAreGettingSaved(): void
    {
        $transfer = TransferAssembler::new()->withAmount('340')->assemble();
        self::bootKernel();

        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        $messageBus->dispatch(new SaveTransfer($transfer));

        static::assertIsNumeric($transfer->getId());
    }
}
