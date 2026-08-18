<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Message\RefundLessonBooking;
use App\MessageHandler\RefundLessonBookingHandler;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class RefundLessonBookingHandlerTest extends KernelTestCase
{
    public function testUserRequestChangesPaymentStatus(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $customer = UserAssembler::new()->withName('Anna Kowalska')->assemble();
        $lesson = LessonAssembler::new()
            ->withTitle('Sensoplastyka')
            ->withSchedule(Clock::get()->now()->modify('+2 days'))
            ->assemble();
        $payment = PaymentAssembler::new()->withUser($customer)->withStatus(Payment::STATUS_PAID)->assemble();
        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        foreach ([$customer, $lesson, $payment, $booking] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $handler = $container->get(RefundLessonBookingHandler::class);
        $handler(new RefundLessonBooking($booking->getId(), $lesson->getId(), $customer, 'Proszę o zwrot.'));
        $em->flush();

        static::assertSame(Payment::STATUS_REFUND_REQUESTED, $payment->getStatus());
        static::assertSame('Proszę o zwrot.', $payment->getRefundRequestMessage());
        static::assertTrue($payment->isRefundRequestedViaUserPanel());
    }
}
