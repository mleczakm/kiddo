<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Entity\Payment;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

abstract class MatchPaymentForTransferHandlerTestCase extends KernelTestCase
{
    use InteractsWithMessenger;

    protected EntityManagerInterface $entityManager;

    protected MessageBusInterface $messageBus;

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

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $messageBus = $container->get(MessageBusInterface::class);
        static::assertInstanceOf(MessageBusInterface::class, $messageBus);
        $this->messageBus = $messageBus;
    }

    protected function setupPendingPayment(): void
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
