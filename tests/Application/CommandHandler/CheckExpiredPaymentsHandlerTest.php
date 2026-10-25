<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\CheckExpiredPayments;
use App\Application\CommandHandler\CheckExpiredPaymentsHandler;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class CheckExpiredPaymentsHandlerTest extends KernelTestCase
{
    public function testExpiresPendingPaymentsOlderThanTheWindowAndCancelsTheirBookings(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        // Well past the 30-minute window.
        $longAgo = $now->modify('-2 hours');
        $recently = $now->modify('-5 minutes');

        $user = UserAssembler::new()->assemble();
        $em->persist($user);

        // Payment A: pending, created well past the 30-minute window -> must expire
        // and its booking must be auto-cancelled by BookingConfirmationSubscriber.
        $lessonA = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($now->modify('+2 days'))
            ->assemble();
        $em->persist($lessonA);
        $paymentA = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->withCreatedAt($longAgo)
            ->assemble();
        $em->persist($paymentA);
        $bookingA = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($paymentA)
            ->withLessons($lessonA)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();
        $em->persist($bookingA);

        // Payment B: pending, but created recently -> must remain pending.
        $lessonB = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($now->modify('+2 days'))
            ->assemble();
        $em->persist($lessonB);
        $paymentB = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->withCreatedAt($recently)
            ->assemble();
        $em->persist($paymentB);
        $bookingB = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($paymentB)
            ->withLessons($lessonB)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();
        $em->persist($bookingB);

        $em->flush();
        // Payment::$bookings is the inverse side of the association and was
        // never populated in memory (only Booking::$payment, the owning
        // side, was set above) - clear so the handler loads everything fresh
        // from the database, the same way it would for a real scheduled run.
        $em->clear();

        /** @var CheckExpiredPaymentsHandler $handler */
        $handler = self::getContainer()->get(CheckExpiredPaymentsHandler::class);
        $handler(new CheckExpiredPayments($now));
        // Unlike CheckExpiredBookingsHandler (which flushes as a side effect of
        // its own activity-log write), this handler does not flush itself.
        $em->flush();

        $em->clear();

        $reloadedPaymentA = $em->find(Payment::class, $paymentA->getId());
        $reloadedBookingA = $em->find(Booking::class, $bookingA->getId());
        static::assertInstanceOf(Payment::class, $reloadedPaymentA);
        static::assertInstanceOf(Booking::class, $reloadedBookingA);
        static::assertSame(Payment::STATUS_EXPIRED, $reloadedPaymentA->getStatus());
        static::assertSame(
            Booking::STATUS_CANCELLED,
            $reloadedBookingA->getStatus(),
            'BookingConfirmationSubscriber cancels bookings when their payment expires',
        );

        $reloadedPaymentB = $em->find(Payment::class, $paymentB->getId());
        $reloadedBookingB = $em->find(Booking::class, $bookingB->getId());
        static::assertInstanceOf(Payment::class, $reloadedPaymentB);
        static::assertInstanceOf(Booking::class, $reloadedBookingB);
        static::assertSame(Payment::STATUS_PENDING, $reloadedPaymentB->getStatus());
        static::assertSame(Booking::STATUS_PENDING, $reloadedBookingB->getStatus());
    }

    public function testPayOnPlacePaymentsAreNeverExpiredEvenPastTheWindow(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $longAgo = $now->modify('-2 hours');

        $user = UserAssembler::new()->assemble();
        $em->persist($user);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($now->modify('+2 days'))
            ->assemble();
        $em->persist($lesson);

        // Pending pay-on-place payment created well past the 30-minute window: the
        // admin booked someone in who will pay cash at the door, so it must
        // stay pending and its active booking must stay active.
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->withMethod(PaymentMethod::PAY_ON_PLACE)
            ->withCreatedAt($longAgo)
            ->assemble();
        $em->persist($payment);
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $em->persist($booking);

        $em->flush();
        $em->clear();

        /** @var CheckExpiredPaymentsHandler $handler */
        $handler = self::getContainer()->get(CheckExpiredPaymentsHandler::class);
        $handler(new CheckExpiredPayments($now));
        $em->flush();
        $em->clear();

        $reloadedPayment = $em->find(Payment::class, $payment->getId());
        $reloadedBooking = $em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertInstanceOf(Booking::class, $reloadedBooking);
        static::assertSame(Payment::STATUS_PENDING, $reloadedPayment->getStatus());
        static::assertSame(Booking::STATUS_ACTIVE, $reloadedBooking->getStatus());
    }

    public function testPaymentExpiresRightAfterTheThirtyMinuteWindowEvenOverAWeekend(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $em->persist($user);

        // Created Saturday 2025-01-04 12:00 UTC: the window is wall-clock time,
        // so weekends and holidays don't pause it.
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->withCreatedAt(new \DateTimeImmutable('2025-01-04 12:00:00', new \DateTimeZone('UTC')))
            ->assemble();
        $em->persist($payment);
        $em->flush();
        $paymentId = $payment->getId();
        $em->clear();

        /** @var CheckExpiredPaymentsHandler $handler */
        $handler = self::getContainer()->get(CheckExpiredPaymentsHandler::class);

        $handler(new CheckExpiredPayments(new \DateTimeImmutable('2025-01-04 12:29:00', new \DateTimeZone('UTC'))));
        $em->flush();
        $em->clear();
        $stillPending = $em->find(Payment::class, $paymentId);
        static::assertInstanceOf(Payment::class, $stillPending);
        static::assertSame(Payment::STATUS_PENDING, $stillPending->getStatus());

        $handler(new CheckExpiredPayments(new \DateTimeImmutable('2025-01-04 12:31:00', new \DateTimeZone('UTC'))));
        $em->flush();
        $em->clear();
        $expired = $em->find(Payment::class, $paymentId);
        static::assertInstanceOf(Payment::class, $expired);
        static::assertSame(Payment::STATUS_EXPIRED, $expired->getStatus());
    }
}
