<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Repository;

use App\Entity\Payment;
use App\Infrastructure\Doctrine\Repository\PaymentRepository;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
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
        $paymentRepository = $container->get(PaymentRepository::class);
        static::assertInstanceOf(PaymentRepository::class, $paymentRepository);
        $this->paymentRepository = $paymentRepository;
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
        /** @var EntityManagerInterface $em */
        $registry = self::getContainer()->get('doctrine');
        static::assertInstanceOf(\Doctrine\Persistence\ManagerRegistry::class, $registry);
        $em = $registry->getManager();
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
