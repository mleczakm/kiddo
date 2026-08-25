<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Application\Workflow\BookingStateMachineInterface;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\Booking;
use App\Entity\Payment;
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
final class BookingConfirmationSubscriberTest extends KernelTestCase
{
    public function testReactivatesAnAlreadyCancelledBookingWhenItsLatePaymentCompletes(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var BookingStateMachineInterface $bookingStateMachine */
        $bookingStateMachine = $container->get(BookingStateMachineInterface::class);
        /** @var PaymentStateMachineInterface $paymentStateMachine */
        $paymentStateMachine = $container->get(PaymentStateMachineInterface::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $user = UserAssembler::new()->assemble();
        $em->persist($user);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($now->modify('+2 days'))
            ->assemble();
        $em->persist($lesson);

        // Zero amount so Payment::isPaid() (amount <= amountPaid) is
        // trivially satisfied without needing a matching Transfer or an
        // authenticated admin - this test is about the reactivation
        // behavior, not the payment-matching guard.
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::zero('PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $em->persist($payment);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();
        $lesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        // Simulate a booking that was already auto-cancelled for lack of
        // payment (e.g. by CheckExpiredBookingsHandler) before this payment
        // was matched.
        $bookingStateMachine->apply($booking, 'cancel');
        $em->flush();
        $em->clear();

        $cancelledBooking = $em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Booking::class, $cancelledBooking);
        static::assertSame(Booking::STATUS_CANCELLED, $cancelledBooking->getStatus());

        $pendingPayment = $em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $pendingPayment);

        // The payment is later confirmed - e.g. a bank transfer is matched -
        // which fires workflow.payment.completed.pay and, in turn,
        // BookingConfirmationSubscriber::onPaymentCompleted.
        $paymentStateMachine->apply($pendingPayment, Payment::TRANSITION_PAY);
        $em->flush();
        $em->clear();

        $paidPayment = $em->find(Payment::class, $payment->getId());
        $reactivatedBooking = $em->find(Booking::class, $booking->getId());

        static::assertInstanceOf(Payment::class, $paidPayment);
        static::assertInstanceOf(Booking::class, $reactivatedBooking);
        static::assertSame(Payment::STATUS_PAID, $paidPayment->getStatus());
        static::assertSame(
            Booking::STATUS_ACTIVE,
            $reactivatedBooking->getStatus(),
            'A late but valid payment should revive an already-cancelled booking',
        );
    }
}
