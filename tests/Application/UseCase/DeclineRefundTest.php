<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Application\UseCase\DeclineRefund;
use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Entity\Booking;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\RefundRequest;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
final class DeclineRefundTest extends KernelTestCase
{
    use InteractsWithMailer;
    use InteractsWithMessenger;

    private EntityManagerInterface $em;

    private DeclineRefund $declineRefund;

    private RefundRequestRepositoryInterface $refundRequestRepository;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var DeclineRefund $declineRefund */
        $declineRefund = $container->get(DeclineRefund::class);
        $this->declineRefund = $declineRefund;
        /** @var RefundRequestRepositoryInterface $refundRequestRepository */
        $refundRequestRepository = $container->get(RefundRequestRepositoryInterface::class);
        $this->refundRequestRepository = $refundRequestRepository;
    }

    /**
     * @return array{RefundRequest, Payment, Booking}
     */
    private function createPendingRefundRequestOnACancelledBooking(): array
    {
        $customer = UserAssembler::new()->withName('Klient Testowy')->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+3 days'))->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(120, 'PLN'))
            ->withStatus(Payment::STATUS_REFUND_REQUESTED)
            ->assemble();
        // Reflects the real CancelAndRequestRefund composition: the booking
        // is already cancelled by the time a refund request exists.
        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_CANCELLED)
            ->assemble();
        $refundRequest = new RefundRequest(
            payment: $payment,
            booking: $booking,
            lesson: $lesson,
            requestedAmount: $payment->getAmount(),
            requestedBy: $customer,
            requestMessage: 'Zmiana planów',
        );

        foreach ([$customer, $lesson, $payment, $booking, $refundRequest] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return [$refundRequest, $payment, $booking];
    }

    public function testDecliningMarksPaymentPaidRequestDeclinedLogsActivityAndNotifies(): void
    {
        [$refundRequest, $payment] = $this->createPendingRefundRequestOnACancelledBooking();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->declineRefund)($refundRequest->getId(), $adminId, 'Poza terminem zwrotu');
        $this->transport('async')->process();

        $this->em->clear();

        $reloadedPayment = $this->em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_PAID, $reloadedPayment->getStatus());

        $reloadedRequest = $this->em->find(RefundRequest::class, $refundRequest->getId());
        static::assertInstanceOf(RefundRequest::class, $reloadedRequest);
        static::assertSame(RefundRequest::STATUS_DECLINED, $reloadedRequest->getStatus());
        static::assertFalse($reloadedRequest->isPending());
        static::assertSame($adminId, $reloadedRequest->getDecidedBy()?->getId());
        static::assertSame('Poza terminem zwrotu', $reloadedRequest->getDecisionNote());

        $activityLogs = $this->em
            ->getRepository(ActivityLog::class)
            ->findBy([
                'type' => ActivityType::REFUND_DECLINED,
            ]);
        static::assertCount(1, $activityLogs);

        $this->mailer()->assertSentEmailCount(1);

        $notifications = $this->em->getRepository(Notification::class)->findAll();
        static::assertCount(1, $notifications);
    }

    public function testDeclinedRequestRemainsInHistoryInsteadOfBeingDeleted(): void
    {
        [$refundRequest, $payment] = $this->createPendingRefundRequestOnACancelledBooking();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->declineRefund)($refundRequest->getId(), $adminId, null);

        $this->em->clear();

        $reloadedRequest = $this->em->find(RefundRequest::class, $refundRequest->getId());
        static::assertInstanceOf(RefundRequest::class, $reloadedRequest, 'Declining must not delete the request');
        static::assertSame(RefundRequest::STATUS_DECLINED, $reloadedRequest->getStatus());

        $reloadedPayment = $this->em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertNull(
            $this->refundRequestRepository->findPendingForPayment($reloadedPayment),
            'A declined request is no longer pending, so it drops out of the admin queue',
        );
    }

    public function testDecliningDoesNotReactivateTheBooking(): void
    {
        [$refundRequest, , $booking] = $this->createPendingRefundRequestOnACancelledBooking();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->declineRefund)($refundRequest->getId(), $adminId, null);

        $this->em->clear();

        $reloadedBooking = $this->em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Booking::class, $reloadedBooking);
        static::assertSame(
            Booking::STATUS_CANCELLED,
            $reloadedBooking->getStatus(),
            'Declining a refund is a money decision only - it must not touch the booking/seat',
        );
    }

    public function testNonAdminCannotDeclineARefund(): void
    {
        [$refundRequest] = $this->createPendingRefundRequestOnACancelledBooking();
        $plainUser = UserAssembler::new()->assemble();
        $this->em->persist($plainUser);
        $this->em->flush();
        $plainUserId = $plainUser->getId();
        static::assertNotNull($plainUserId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not authorized');

        ($this->declineRefund)($refundRequest->getId(), $plainUserId, null);
    }

    public function testCannotDeclineARequestThatWasAlreadyDecided(): void
    {
        [$refundRequest] = $this->createPendingRefundRequestOnACancelledBooking();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->declineRefund)($refundRequest->getId(), $adminId, 'First decision');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already been decided');

        ($this->declineRefund)($refundRequest->getId(), $adminId, 'Second decision attempt');
    }
}
