<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Application\Command\MatchPaymentForTransfer;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Stage 6: Payment paid -> Order paid, for payments dual-written to an order
 * (Stage 5). Legacy payments without an order (the common case today, since
 * commerce_order_write defaults off) must be unaffected.
 */
#[Group('functional')]
final class OrderPaymentPropagationSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private MessageBusInterface $messageBus;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var MessageBusInterface $messageBus */
        $messageBus = $container->get(MessageBusInterface::class);
        $this->messageBus = $messageBus;
    }

    public function testOrderIsMarkedPaidWhenItsPaymentIsPaid(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->em->persist($user);

        $orderId = new Ulid();
        $order = new CustomerOrder(
            id: $orderId,
            orderNumber: 'ORD-PROPAGATION-TEST',
            customerId: 1,
            status: CustomerOrder::STATUS_PLACED,
            currency: 'PLN',
            subtotalMinor: 10_000,
            discountTotalMinor: 0,
            totalMinor: 10_000,
            placedAt: new \DateTimeImmutable(),
            expiresAt: null,
            checkoutKey: 'checkout-propagation-test',
            source: CustomerOrder::SOURCE_FAST_TRACK,
        );
        $this->em->persist($order);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $payment->setOrderId($orderId);
        $this->em->persist($payment);

        $paymentCode = new PaymentCode($payment, 'PROP');
        $this->em->persist($paymentCode);

        $transfer = TransferAssembler::new()->withTitle('PROP order propagation')->withAmount('100.00')->assemble();
        $this->em->persist($transfer);
        $this->em->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $this->em->refresh($payment);
        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());

        $this->em->clear();
        $reloadedOrder = $this->em->find(CustomerOrder::class, $orderId);
        static::assertInstanceOf(CustomerOrder::class, $reloadedOrder);
        static::assertSame(CustomerOrder::STATUS_PAID, $reloadedOrder->getStatus());
    }

    public function testLegacyPaymentWithoutAnOrderIsUnaffected(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->em->persist($user);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        static::assertNull($payment->getOrderId());
        $this->em->persist($payment);

        $paymentCode = new PaymentCode($payment, 'NOOR');
        $this->em->persist($paymentCode);

        $transfer = TransferAssembler::new()->withTitle('NOOR no order')->withAmount('100.00')->assemble();
        $this->em->persist($transfer);
        $this->em->flush();

        $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $this->em->refresh($payment);
        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
    }
}
