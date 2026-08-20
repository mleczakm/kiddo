<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Application\UseCase\CancelAndRequestRefund;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class CancelAndRequestRefundTest extends KernelTestCase
{
    public function testAnIneligibleRefundRequestLeavesTheBookingUncancelled(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $customer = UserAssembler::new()->assemble();
        // Scheduled within the 24h boundary, so a non-admin refund request
        // is rejected.
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+2 hours'))->assemble();
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
        $customerId = $customer->getId();
        static::assertNotNull($customerId);

        /** @var CancelAndRequestRefund $useCase */
        $useCase = $container->get(CancelAndRequestRefund::class);

        try {
            $useCase($booking->getId(), $lesson->getId(), $customerId, 'Zmiana planów');
            static::fail('Expected the refund request to be rejected as ineligible.');
        } catch (\RuntimeException $exception) {
            static::assertStringContainsString('24h', $exception->getMessage());
        }

        $em->clear();

        $reloadedBooking = $em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Booking::class, $reloadedBooking);
        static::assertSame(
            Booking::STATUS_ACTIVE,
            $reloadedBooking->getStatus(),
            'Cancellation must not commit when the refund request it was paired with was rejected',
        );

        $reloadedPayment = $em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_PAID, $reloadedPayment->getStatus());

        /** @var RefundRequestRepositoryInterface $refundRequestRepository */
        $refundRequestRepository = $container->get(RefundRequestRepositoryInterface::class);
        static::assertNull($refundRequestRepository->findPendingForPayment($reloadedPayment));
    }
}
