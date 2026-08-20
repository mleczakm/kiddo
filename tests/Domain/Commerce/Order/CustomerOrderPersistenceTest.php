<?php

declare(strict_types=1);

namespace App\Tests\Domain\Commerce\Order;

use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Domain\Commerce\Order\OrderLineAdjustment;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Stage 4 of the commerce rollout plan is additive schema only - nothing in
 * the application uses these tables yet. This test exists purely to prove
 * the XML mapping (a new pattern for this codebase) actually round-trips
 * through Doctrine correctly, beyond what `doctrine:schema:validate` checks
 * (which only validates metadata consistency, not real persistence).
 */
#[Group('functional')]
final class CustomerOrderPersistenceTest extends KernelTestCase
{
    public function testCustomerOrderWithLinesAndAdjustmentsRoundTripsThroughDoctrine(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $orderId = new Ulid();
        $order = new CustomerOrder(
            id: $orderId,
            orderNumber: 'ORD-' . $orderId->toBase32(),
            customerId: 1,
            status: CustomerOrder::STATUS_PLACED,
            currency: 'PLN',
            subtotalMinor: 10_000,
            discountTotalMinor: 500,
            totalMinor: 9500,
            placedAt: new \DateTimeImmutable('2026-08-20 12:00:00'),
            expiresAt: null,
            checkoutKey: 'checkout-' . $orderId->toBase32(),
            source: CustomerOrder::SOURCE_FAST_TRACK,
        );

        $lineId = new Ulid();
        $line = new OrderLine(
            id: $lineId,
            orderId: $orderId,
            lessonId: new Ulid(),
            seriesId: null,
            ticketType: 'one_time',
            title: 'Sensoplastyka',
            scheduleDescription: 'Środa 10:00',
            participantId: null,
            basePriceMinor: 10_000,
            finalPriceMinor: 9500,
            currency: 'PLN',
            pricingQuoteHash: 'quote-hash-abc',
            bookingId: null,
        );

        $adjustment = new OrderLineAdjustment(
            id: new Ulid(),
            orderLineId: $lineId,
            type: OrderLineAdjustment::TYPE_FIXED_AMOUNT,
            amountMinor: -500,
            label: 'Kod promocyjny WELCOME',
        );

        $em->persist($order);
        $em->persist($line);
        $em->persist($adjustment);
        $em->flush();
        $em->clear();

        $reloadedOrder = $em->find(CustomerOrder::class, $orderId);
        static::assertInstanceOf(CustomerOrder::class, $reloadedOrder);
        static::assertSame(1, $reloadedOrder->getCustomerId());
        static::assertSame(CustomerOrder::STATUS_PLACED, $reloadedOrder->getStatus());
        static::assertSame('PLN', $reloadedOrder->getCurrency());
        static::assertSame(10_000, $reloadedOrder->getSubtotalMinor());
        static::assertSame(500, $reloadedOrder->getDiscountTotalMinor());
        static::assertSame(9500, $reloadedOrder->getTotalMinor());
        static::assertSame(CustomerOrder::SOURCE_FAST_TRACK, $reloadedOrder->getSource());
        static::assertSame(1, $reloadedOrder->getVersion(), 'Optimistic-lock version must default to 1 on insert');
        static::assertNull($reloadedOrder->getExpiresAt());

        $reloadedLine = $em->find(OrderLine::class, $lineId);
        static::assertInstanceOf(OrderLine::class, $reloadedLine);
        static::assertTrue($reloadedLine->getOrderId()->equals($orderId));
        static::assertSame('Sensoplastyka', $reloadedLine->getTitle());
        static::assertSame(9500, $reloadedLine->getFinalPriceMinor());
        static::assertNull($reloadedLine->getBookingId());

        $reloadedAdjustments = $em->getRepository(OrderLineAdjustment::class)->findBy([
            'orderLineId' => $lineId,
        ]);
        static::assertCount(1, $reloadedAdjustments);
        static::assertSame(-500, $reloadedAdjustments[0]->getAmountMinor());
    }

    public function testDuplicateOrderNumberViolatesTheUniqueConstraint(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $makeOrder = static fn(Ulid $id, string $checkoutKey): CustomerOrder => new CustomerOrder(
            id: $id,
            orderNumber: 'ORD-DUPLICATE-TEST',
            customerId: 1,
            status: CustomerOrder::STATUS_PLACED,
            currency: 'PLN',
            subtotalMinor: 1000,
            discountTotalMinor: 0,
            totalMinor: 1000,
            placedAt: new \DateTimeImmutable(),
            expiresAt: null,
            checkoutKey: $checkoutKey,
            source: CustomerOrder::SOURCE_FAST_TRACK,
        );

        $em->persist($makeOrder(new Ulid(), 'checkout-key-1'));
        $em->flush();

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $em->persist($makeOrder(new Ulid(), 'checkout-key-2'));
        $em->flush();
    }

    public function testNegativeTotalViolatesTheCheckConstraint(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $order = new CustomerOrder(
            id: new Ulid(),
            orderNumber: 'ORD-NEGATIVE-TOTAL-TEST',
            customerId: 1,
            status: CustomerOrder::STATUS_PLACED,
            currency: 'PLN',
            subtotalMinor: 1000,
            discountTotalMinor: 0,
            totalMinor: -100,
            placedAt: new \DateTimeImmutable(),
            expiresAt: null,
            checkoutKey: 'checkout-negative-total',
            source: CustomerOrder::SOURCE_FAST_TRACK,
        );

        $em->persist($order);

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $em->flush();
    }

    public function testNullOrderIdOnPaymentAndNullOrderLineIdOnBookingAreTheDefault(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = \App\Tests\Assembler\UserAssembler::new()->assemble();
        $lesson = \App\Tests\Assembler\LessonAssembler::new()->assemble();
        $payment = \App\Tests\Assembler\PaymentAssembler::new()->withUser($user)->assemble();
        $booking = \App\Tests\Assembler\BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->assemble();

        foreach ([$user, $lesson, $payment, $booking] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();

        $reloadedPayment = $em->find(\App\Entity\Payment::class, $payment->getId());
        static::assertInstanceOf(\App\Entity\Payment::class, $reloadedPayment);
        static::assertNull($reloadedPayment->getOrderId());

        $reloadedBooking = $em->find(\App\Entity\Booking::class, $booking->getId());
        static::assertInstanceOf(\App\Entity\Booking::class, $reloadedBooking);
        static::assertNull($reloadedBooking->getOrderLineId());
    }
}
