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
 * Characterizes a known gap: the booking-expiry window (60 minutes) is much
 * shorter than the payment-expiry window (24 hours). A booking can therefore
 * be auto-cancelled for lack of payment while its payment is still "pending"
 * and able to accept a matching transfer. This test documents the current
 * result of that race - it is not (yet) the desired behavior - so that a
 * future fix has a test to change deliberately rather than by accident.
 */
#[Group('functional')]
final class ExpiryWindowInteractionTest extends KernelTestCase
{
    public function testLateTransferPaysAPaymentWhoseBookingWasAlreadyAutoCancelled(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var MessageBusInterface $messageBus */
        $messageBus = $container->get(MessageBusInterface::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        // Past the 60-minute booking-expiry window, but well within the 24h
        // payment-expiry window.
        $createdAt = $now->modify('-90 minutes');

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

        // The 60-minute sweep runs first and auto-cancels the booking; the
        // payment is untouched by it (see CheckExpiredBookingsHandler).
        /** @var CheckExpiredBookingsHandler $handler */
        $handler = $container->get(CheckExpiredBookingsHandler::class);
        $handler(new CheckExpiredBookings($now->modify('-60 minutes')));

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
        $stillCancelledBooking = $em->find(Booking::class, $booking->getId());

        static::assertInstanceOf(Payment::class, $paidPayment);
        static::assertInstanceOf(Booking::class, $stillCancelledBooking);

        // Current (undesirable) result: the payment is marked paid...
        static::assertSame(Payment::STATUS_PAID, $paidPayment->getStatus());
        // ...but BookingConfirmationSubscriber's "confirm" transition only
        // applies from the "pending" state, so the already-cancelled booking
        // is left cancelled - money received for a seat that was given up.
        static::assertSame(Booking::STATUS_CANCELLED, $stillCancelledBooking->getStatus());
    }
}
