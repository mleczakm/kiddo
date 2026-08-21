<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Commerce;

use App\Application\Service\Commerce\OrderItemSelection;
use App\Application\Service\Commerce\OrderPlacementService;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\TicketType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Stage 10 of the commerce rollout plan: OrderPlacementService is the one
 * place both the fast-reservation path and cart checkout place an order -
 * these tests focus on what's new for a cart-style, multi-item placement
 * (one shared Payment/PaymentCode across several bookings), since the
 * single-item case is already covered by PlaceSingleReservation's own
 * tests.
 */
#[Group('functional')]
final class OrderPlacementServiceTest extends KernelTestCase
{
    public function testPlacingSeveralItemsSharesOnePaymentAndWritesOneLinePerItem(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var OrderPlacementService $service */
        $service = self::getContainer()->get(OrderPlacementService::class);

        $user = UserAssembler::new()->assemble();
        $lessonA = LessonAssembler::new()->withTitle('Sensoplastyka')->assemble();
        $lessonB = LessonAssembler::new()->withTitle('Origami')->assemble();
        $em->persist($user);
        $em->persist($lessonA);
        $em->persist($lessonB);
        $em->flush();

        $ticketA = $lessonA->getMatchingTicketOption(TicketType::ONE_TIME->value);
        $ticketB = $lessonB->getMatchingTicketOption(TicketType::ONE_TIME->value);

        $result = $service->place(user: $user, source: CustomerOrder::SOURCE_CART, paymentCode: 'CRT1', items: [
            new OrderItemSelection($lessonA, $ticketA, null, null),
            new OrderItemSelection($lessonB, $ticketB, null, null),
        ]);
        $em->flush();

        static::assertNotNull($result->order);
        static::assertSame(CustomerOrder::SOURCE_CART, $result->order->getSource());
        static::assertCount(2, $result->orderLines);
        static::assertCount(2, $result->bookings);

        $expectedTotal = $ticketA->price->getMinorAmount()->toInt() + $ticketB->price->getMinorAmount()->toInt();
        static::assertSame($expectedTotal, $result->order->getTotalMinor());
        static::assertSame($expectedTotal, $result->payment->getAmount()->getMinorAmount()->toInt());

        foreach ($result->bookings as $booking) {
            static::assertTrue($booking->getPayment() === $result->payment);
        }
        static::assertCount(2, $result->payment->getBookings());
        static::assertNotNull($result->payment->getPaymentCode());
        static::assertSame('CRT1', $result->payment->getPaymentCode()?->getCode());

        $lineOrderIds = array_map(static fn($line) => (string) $line->getOrderId(), $result->orderLines);
        static::assertSame([(string) $result->order->getId(), (string) $result->order->getId()], $lineOrderIds);
    }

    public function testWriteOrderFalseStillCreatesBookingsAndAPaymentButNoOrder(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var OrderPlacementService $service */
        $service = self::getContainer()->get(OrderPlacementService::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();

        $ticket = $lesson->getMatchingTicketOption(TicketType::ONE_TIME->value);

        $result = $service->place(
            user: $user,
            source: CustomerOrder::SOURCE_FAST_TRACK,
            paymentCode: 'NOOR',
            items: [new OrderItemSelection($lesson, $ticket, null, null)],
            writeOrder: false,
        );

        static::assertNull($result->order);
        static::assertSame([], $result->orderLines);
        static::assertCount(1, $result->bookings);
        static::assertNotNull($result->payment->getPaymentCode());
    }

    public function testPlacingWithNoItemsThrows(): void
    {
        self::bootKernel();
        /** @var OrderPlacementService $service */
        $service = self::getContainer()->get(OrderPlacementService::class);
        $user = UserAssembler::new()->assemble();

        $this->expectException(\InvalidArgumentException::class);
        $service->place($user, CustomerOrder::SOURCE_CART, 'EMPT', []);
    }
}
