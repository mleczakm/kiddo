<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Commerce;

use App\Application\Service\Commerce\FastTrackOrderFactory;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\Child;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FastTrackOrderFactoryTest extends TestCase
{
    public function testOneTimeTicketProducesAnOrderLineAgainstTheSingleLesson(): void
    {
        $user = UserAssembler::new()->withId(42)->assemble();
        $lesson = LessonAssembler::new()->withTitle('Sensoplastyka')->assemble();
        $ticketOption = new TicketOption(
            TicketType::ONE_TIME,
            Money::of('95.50', 'PLN'),
            'Bilet jednorazowy',
            TicketReschedulePolicy::ONETIME_24H_BEFORE,
        );
        $payment = PaymentAssembler::new()->withUser($user)->withAmount($ticketOption->price)->assemble();
        $booking = BookingAssembler::new()->withUser($user)->withPayment($payment)->withLessons($lesson)->assemble();

        [$order, $line] = new FastTrackOrderFactory()->create($booking, $payment, $lesson, $ticketOption, $user);

        static::assertSame(42, $order->getCustomerId());
        static::assertSame(CustomerOrder::STATUS_PLACED, $order->getStatus());
        static::assertSame(CustomerOrder::SOURCE_FAST_TRACK, $order->getSource());
        static::assertSame('PLN', $order->getCurrency());
        static::assertSame(9550, $order->getSubtotalMinor());
        static::assertSame(0, $order->getDiscountTotalMinor());
        static::assertSame(9550, $order->getTotalMinor());
        static::assertStringStartsWith('FT-', $order->getOrderNumber());

        static::assertTrue($line->getOrderId()->equals($order->getId()));
        static::assertTrue($line->getLessonId()?->equals($lesson->getId()));
        static::assertNull($line->getSeriesId());
        static::assertSame(TicketType::ONE_TIME->value, $line->getTicketType());
        static::assertSame('Sensoplastyka', $line->getTitle());
        static::assertSame(9550, $line->getBasePriceMinor());
        static::assertSame(9550, $line->getFinalPriceMinor());
        static::assertTrue($line->getBookingId()?->equals($booking->getId()));
        static::assertNull($line->getParticipantId());
    }

    public function testCarnetTicketRecordsTheSeriesRatherThanASingleLesson(): void
    {
        $user = UserAssembler::new()->withId(7)->assemble();
        $series = SeriesAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withSeries($series)->assemble();
        $ticketOption = new TicketOption(
            TicketType::CARNET_4,
            Money::of('300', 'PLN'),
            'Karnet 4 wejścia',
            TicketReschedulePolicy::ONETIME_24H_BEFORE,
        );
        $payment = PaymentAssembler::new()->withUser($user)->withAmount($ticketOption->price)->assemble();
        $booking = BookingAssembler::new()->withUser($user)->withPayment($payment)->withLessons($lesson)->assemble();

        [, $line] = new FastTrackOrderFactory()->create($booking, $payment, $lesson, $ticketOption, $user);

        static::assertNull($line->getLessonId());
        static::assertTrue($line->getSeriesId()?->equals($series->getId()));
        static::assertSame(TicketType::CARNET_4->value, $line->getTicketType());
    }

    public function testParticipantIdReflectsTheBookedChild(): void
    {
        $user = UserAssembler::new()->withId(1)->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $ticketOption = $lesson->getMatchingTicketOption(TicketType::ONE_TIME->value);
        $payment = PaymentAssembler::new()->withUser($user)->withAmount($ticketOption->price)->assemble();
        $child = new Child($user, 'Zosia', new \DateTimeImmutable('2020-01-01'));
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withChild($child)
            ->assemble();

        [, $line] = new FastTrackOrderFactory()->create($booking, $payment, $lesson, $ticketOption, $user);

        static::assertTrue($line->getParticipantId()?->equals($child->getId()));
    }
}
