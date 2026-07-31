<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Booking;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[Group('unit')]
final class BookingReschedulePolicyTest extends TestCase
{
    use ClockSensitiveTrait;

    #[Test]
    public function oneTimePolicyAllowsSingleRescheduleBefore24h(): void
    {
        self::mockTime('2026-07-01 10:00:00');

        $lesson = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('2026-07-10 10:00:00'))
            ->withTicketOptions([
                new TicketOption(
                    TicketType::ONE_TIME,
                    Money::of(60, 'PLN'),
                    'One time',
                    TicketReschedulePolicy::ONETIME_24H_BEFORE,
                ),
            ])
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser(UserAssembler::new()->assemble())
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        self::assertTrue($booking->canRescheduleLesson($lesson));
        self::assertTrue($booking->canRequestRefundForLesson($lesson));
    }

    #[Test]
    public function oneTimePolicyBlocksSecondReschedule(): void
    {
        self::mockTime('2026-07-01 10:00:00');

        $from = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('2026-07-10 10:00:00'))
            ->withTicketOptions([
                new TicketOption(
                    TicketType::ONE_TIME,
                    Money::of(60, 'PLN'),
                    'One time',
                    TicketReschedulePolicy::ONETIME_24H_BEFORE,
                ),
            ])
            ->assemble();
        $to = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('2026-07-17 10:00:00'))
            ->assemble();
        $user = UserAssembler::new()->withId(1)->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($from)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $booking->rescheduleLesson($from, $to, $user);

        self::assertTrue($booking->hasBeenRescheduled());
        self::assertFalse($booking->canRescheduleLesson($to));
    }

    #[Test]
    public function notAllowedPolicyBlocksReschedule(): void
    {
        self::mockTime('2026-07-01 10:00:00');

        $lesson = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('2026-07-10 10:00:00'))
            ->withTicketOptions([
                new TicketOption(
                    TicketType::ONE_TIME,
                    Money::of(60, 'PLN'),
                    'One time',
                    TicketReschedulePolicy::NOT_ALLOWED,
                ),
            ])
            ->assemble();

        $booking = BookingAssembler::new()
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        self::assertFalse($booking->canRescheduleLesson($lesson));
    }

    #[Test]
    public function lateCancelAllowsCancelButNotRefund(): void
    {
        self::mockTime('2026-07-10 09:00:00');

        $lesson = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('2026-07-10 18:00:00'))
            ->assemble();

        $booking = BookingAssembler::new()
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        self::assertTrue($booking->canCancelLesson($lesson));
        self::assertFalse($booking->canRequestRefundForLesson($lesson));
        self::assertFalse($booking->canRescheduleLesson($lesson));
    }
}
