<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class PaymentRepositoryTest extends KernelTestCase
{
    private PaymentRepository $paymentRepository;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->paymentRepository = $container->get(PaymentRepository::class);
    }

    public function testFindPendingWithSearch(): void
    {
        $this->preparePendingPayment();

        // Test cases
        static::assertCount(1, $this->paymentRepository->findPendingWithSearch('Test User'), 'Search by name');
        static::assertCount(1, $this->paymentRepository->findPendingWithSearch('test@example.com'), 'Search by email');
        static::assertCount(1, $this->paymentRepository->findPendingWithSearch('1234'), 'Search by payment code');
        static::assertCount(1, $this->paymentRepository->findPendingWithSearch('150'), 'Search by amount');
        static::assertCount(
            1,
            $this->paymentRepository->findPendingWithSearch(''),
            'Empty search should return all pending',
        );
        static::assertCount(0, $this->paymentRepository->findPendingWithSearch('nonexistent'), 'Search with no match');
    }

    public function testCountPendingPayments(): void
    {
        static::assertSame(0, $this->paymentRepository->countPendingPayments());

        $this->preparePendingPayment();

        static::assertSame(1, $this->paymentRepository->countPendingPayments());
    }

    private function preparePendingPayment(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        // Setup test data
        $user = UserAssembler::new()->withName('Test User')->withEmail('test@example.com')->assemble();
        $em->persist($user);

        $paymentCode = PaymentCodeAssembler::new()->withCode('1234')->assemble();
        $em->persist($paymentCode);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withPaymentCode($paymentCode)
            ->withAmount(Money::of(150, 'PLN')) // 150.00 PLN
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $em->persist($payment);

        $em->flush();
    }
}
