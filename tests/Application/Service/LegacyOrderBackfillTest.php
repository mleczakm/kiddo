<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\LegacyOrderBackfill;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class LegacyOrderBackfillTest extends KernelTestCase
{
    public function testBackfillIsIdempotentAndReconcilesOneOrderPerLegacyPayment(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $backfill = self::getContainer()->get(LegacyOrderBackfill::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        static::assertInstanceOf(LegacyOrderBackfill::class, $backfill);
        $user = UserAssembler::new()->assemble();
        $firstLesson = LessonAssembler::new()->withTitle('First')->assemble();
        $secondLesson = LessonAssembler::new()->withTitle('Second')->assemble();
        $payment = PaymentAssembler::new()->withUser($user)->withAmount(Money::of(101, 'PLN'))->assemble();
        $first = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($firstLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $second = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($secondLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $payment->addBooking($first);
        $payment->addBooking($second);
        foreach ([$user, $firstLesson, $secondLesson, $payment, $first, $second] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $firstReport = $backfill->run();
        $secondReport = $backfill->run();

        static::assertSame(1, $firstReport['ordersCreated']);
        static::assertSame(0, $firstReport['amountDifferenceMinor']);
        static::assertSame(0, $secondReport['ordersCreated']);
        $orders = $em->getRepository(CustomerOrder::class)->findAll();
        $lines = $em->getRepository(OrderLine::class)->findAll();
        static::assertCount(1, $orders);
        static::assertCount(2, $lines);
        static::assertSame(
            10_100,
            array_sum(array_map(static fn(OrderLine $line): int => $line->getFinalPriceMinor(), $lines)),
        );
        static::assertNotNull($payment->getOrderId());
        static::assertNotNull($first->getOrderLineId());
        static::assertNotNull($second->getOrderLineId());
    }
}
