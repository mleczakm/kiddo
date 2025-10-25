<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AgeRange;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Payment;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Currency;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class PaymentTest extends TestCase
{
    public function testIsPaidFalseOnMissingTransfers(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, Currency::ofCountry('PL')));
        $this->assertFalse($payment->isPaid());
    }

    public function testIsPaidTrueOnTransfers(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, Currency::ofCountry('PL')));
        $payment->addTransfer(TransferAssembler::new()->withAmount('100,00')->assemble());

        $this->assertTrue($payment->isPaid());
    }

    public function testAmountMatch(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, Currency::ofCountry('PL')));
        $transfer = TransferAssembler::new()->withAmount('100,00')->assemble();

        $this->assertTrue($payment->amountMatch($transfer));
    }

    public function testGetBookingsSummaryWithNoBookings(): void
    {
        $payment = PaymentAssembler::new()
            ->withUser(UserAssembler::new()->assemble())
            ->assemble();

        $result = $payment->getBookingsSummary();

        $this->assertSame('', $result);
    }

    public function testGetBookingsSummaryWithSingleBooking(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->assemble();

        // Create a lesson with a specific schedule
        $schedule = new \DateTimeImmutable('2025-10-26 15:30:00');
        $lessonMetadata = new LessonMetadata(
            'Yoga Class',
            'Beginner Yoga',
            'yoga',
            'A relaxing yoga session',
            10,
            $schedule,
            60,
            new AgeRange(18, 65),
            'fitness'
        );
        $lesson = new Lesson($lessonMetadata);

        // Create a booking with the lesson
        $booking = new Booking($user, $payment, $lesson);
        $payment->addBooking($booking);

        $result = $payment->getBookingsSummary();

        $this->assertSame('Yoga Class (15:30) 26.10', $result);
    }

    public function testGetBookingsSummaryWithMultipleBookings(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->assemble();

        // Create first lesson
        $schedule1 = new \DateTimeImmutable('2025-10-26 15:30:00');
        $lessonMetadata1 = new LessonMetadata(
            'Yoga Class',
            'Beginner Yoga',
            'yoga',
            'A relaxing yoga session',
            10,
            $schedule1,
            60,
            new AgeRange(18, 65),
            'fitness'
        );
        $lesson1 = new Lesson($lessonMetadata1);

        // Create second lesson on a different date
        $schedule2 = new \DateTimeImmutable('2025-10-28 16:00:00');
        $lessonMetadata2 = new LessonMetadata(
            'Pilates',
            'Advanced Pilates',
            'pilates',
            'Challenging pilates workout',
            10,
            $schedule2,
            60,
            new AgeRange(18, 65),
            'fitness'
        );
        $lesson2 = new Lesson($lessonMetadata2);

        // Create bookings with the lessons
        $booking1 = new Booking($user, $payment, $lesson1);
        $booking2 = new Booking($user, $payment, $lesson2);

        $payment->addBooking($booking1);
        $payment->addBooking($booking2);

        $result = $payment->getBookingsSummary();

        $this->assertSame('Yoga Class (15:30) 26.10, Pilates (16:00) 28.10', $result);
    }

    public function testGetTextSummaryForSingleLesson(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->assemble();

        $schedule = new \DateTimeImmutable('2025-10-26 15:30:00');
        $lessonMetadata = new LessonMetadata(
            'Yoga Class',
            'Beginner Yoga',
            'yoga',
            'A relaxing yoga session',
            10,
            $schedule,
            60,
            new AgeRange(18, 65),
            'fitness'
        );
        $lesson = new Lesson($lessonMetadata);

        $booking = new Booking($user, $payment, $lesson);

        $result = $booking->getTextSummary();

        $this->assertSame('Yoga Class (15:30) 26.10', $result);
    }

    public function testGetTextSummaryForMultipleLessons(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->assemble();

        $schedule1 = new \DateTimeImmutable('2025-10-26 15:30:00');
        $lessonMetadata1 = new LessonMetadata(
            'Yoga Class',
            'Beginner Yoga',
            'yoga',
            'A relaxing yoga session',
            10,
            $schedule1,
            60,
            new AgeRange(18, 65),
            'fitness'
        );
        $lesson1 = new Lesson($lessonMetadata1);

        $schedule2 = new \DateTimeImmutable('2025-10-28 16:00:00');
        $lessonMetadata2 = new LessonMetadata(
            'Pilates',
            'Advanced Pilates',
            'pilates',
            'Challenging pilates workout',
            10,
            $schedule2,
            60,
            new AgeRange(18, 65),
            'fitness'
        );
        $lesson2 = new Lesson($lessonMetadata2);

        $booking = new Booking($user, $payment, $lesson1, $lesson2);
        $payment->addBooking($booking);

        $result = $booking->getTextSummary();

        $this->assertSame('Yoga Class (15:30) 26.10, 28.10', $result);
    }
}
