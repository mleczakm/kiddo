<?php

declare(strict_types=1);

namespace App\Tests\Functional\WholeProcess;

use App\Application\Command\CheckExpiredBookings;
use App\Application\Command\MatchPaymentForTransfer;
use App\Application\CommandHandler\CheckExpiredBookingsHandler;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Characterizes the fixed behavior of the booking/payment expiry race: the
 * booking-expiry window is now 24 *working* hours (weekends don't count,
 * since bank transfers can't settle until the next business day), matching
 * the payment-expiry window's 24h. A transfer can still legitimately arrive
 * after a booking has already been auto-cancelled for lack of payment - this
 * test asserts that BookingConfirmationSubscriber now reactivates that
 * booking instead of leaving money received against a cancelled booking.
 */
#[Group('functional')]
final class ExpiryWindowInteractionTest extends KernelTestCase
{
    public function testLateTransferReactivatesABookingThatWasAlreadyAutoCancelled(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var MessageBusInterface $messageBus */
        $messageBus = $container->get(MessageBusInterface::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        // Comfortably past the 24-working-hour booking-expiry window,
        // regardless of which weekday "now" happens to be in the test run.
        $createdAt = $now->modify('-4 days');

        $user = UserAssembler::new()->assemble();
        $em->persist($user);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($now->modify('+2 days'))
            ->assemble();
        $em->persist($lesson);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(100, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $em->persist($payment);

        $paymentCode = new PaymentCode($payment);
        $reflection = new \ReflectionClass($paymentCode);
        $reflection->getProperty('code')->setValue($paymentCode, 'LATE');
        $em->persist($paymentCode);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_PENDING)
            ->withCreatedAt($createdAt)
            ->assemble();
        $lesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        // The booking-expiry sweep runs first and auto-cancels the booking;
        // the payment is untouched by it (see CheckExpiredBookingsHandler).
        /** @var CheckExpiredBookingsHandler $handler */
        $handler = $container->get(CheckExpiredBookingsHandler::class);
        $handler(new CheckExpiredBookings($now));

        $em->clear();
        $cancelledBooking = $em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Booking::class, $cancelledBooking);
        static::assertSame(Booking::STATUS_CANCELLED, $cancelledBooking->getStatus());

        $pendingPayment = $em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $pendingPayment);
        static::assertSame(Payment::STATUS_PENDING, $pendingPayment->getStatus());

        // A transfer for the exact amount then arrives, still inside the 24h
        // payment window, and matches by code.
        $transfer = TransferAssembler::new()->withTitle('Payment LATE for order')->withAmount('100.00')->assemble();
        $em->persist($transfer);
        $em->flush();

        $messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        $em->clear();
        $paidPayment = $em->find(Payment::class, $payment->getId());
        $reactivatedBooking = $em->find(Booking::class, $booking->getId());

        static::assertInstanceOf(Payment::class, $paidPayment);
        static::assertInstanceOf(Booking::class, $reactivatedBooking);

        static::assertSame(Payment::STATUS_PAID, $paidPayment->getStatus());
        // BookingConfirmationSubscriber falls back to the "reactivate"
        // transition when "confirm" isn't available (i.e. the booking was
        // already cancelled) - money received now correctly restores the seat.
        static::assertSame(Booking::STATUS_ACTIVE, $reactivatedBooking->getStatus());
    }
}
