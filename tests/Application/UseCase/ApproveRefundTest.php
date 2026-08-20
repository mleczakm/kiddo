<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\ApproveRefund;
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
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
final class ApproveRefundTest extends KernelTestCase
{
    use InteractsWithMailer;
    use InteractsWithMessenger;

    private EntityManagerInterface $em;

    private ApproveRefund $approveRefund;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var ApproveRefund $approveRefund */
        $approveRefund = $container->get(ApproveRefund::class);
        $this->approveRefund = $approveRefund;
    }

    /**
     * @return array{RefundRequest, Payment, Booking}
     */
    private function createPendingRefundRequest(): array
    {
        $customer = UserAssembler::new()->withName('Klient Testowy')->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+3 days'))->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(120, 'PLN'))
            ->withStatus(Payment::STATUS_REFUND_REQUESTED)
            ->assemble();
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

    public function testApprovingMarksPaymentRefundedRequestApprovedLogsActivityAndNotifies(): void
    {
        [$refundRequest, $payment] = $this->createPendingRefundRequest();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->approveRefund)($refundRequest->getId(), $adminId, 'Przelew zwrotny wykonany 123');
        $this->transport('async')->process();

        $this->em->clear();

        $reloadedPayment = $this->em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_REFUNDED, $reloadedPayment->getStatus());

        $reloadedRequest = $this->em->find(RefundRequest::class, $refundRequest->getId());
        static::assertInstanceOf(RefundRequest::class, $reloadedRequest);
        static::assertSame(RefundRequest::STATUS_APPROVED, $reloadedRequest->getStatus());
        static::assertFalse($reloadedRequest->isPending());
        static::assertEquals($reloadedRequest->getRequestedAmount(), $reloadedRequest->getApprovedAmount());
        static::assertSame($adminId, $reloadedRequest->getDecidedBy()?->getId());
        static::assertNotNull($reloadedRequest->getDecidedAt());

        $activityLogs = $this->em
            ->getRepository(ActivityLog::class)
            ->findBy([
                'type' => ActivityType::REFUND_APPROVED,
            ]);
        static::assertCount(1, $activityLogs);

        $this->mailer()->assertSentEmailCount(1);

        $notifications = $this->em->getRepository(Notification::class)->findAll();
        static::assertCount(1, $notifications);
    }

    public function testNonAdminCannotApproveARefund(): void
    {
        [$refundRequest] = $this->createPendingRefundRequest();
        $plainUser = UserAssembler::new()->assemble();
        $this->em->persist($plainUser);
        $this->em->flush();
        $plainUserId = $plainUser->getId();
        static::assertNotNull($plainUserId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not authorized');

        ($this->approveRefund)($refundRequest->getId(), $plainUserId, null);
    }

    public function testCannotApproveARequestThatWasAlreadyDecided(): void
    {
        [$refundRequest] = $this->createPendingRefundRequest();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->approveRefund)($refundRequest->getId(), $adminId, 'First decision');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already been decided');

        ($this->approveRefund)($refundRequest->getId(), $adminId, 'Second decision attempt');
    }

    public function testTwoAdminsCannotDecideTheSameRequestDifferently(): void
    {
        [$refundRequest] = $this->createPendingRefundRequest();
        $refundRequestId = $refundRequest->getId();

        $admin2 = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin2);
        $this->em->flush();

        // Admin 2 loaded the refund request into memory at optimistic-lock
        // version 1, same as admin 1 would have.
        $copyForAdmin2 = $this->em->find(RefundRequest::class, $refundRequestId);
        static::assertInstanceOf(RefundRequest::class, $copyForAdmin2);

        // Admin 1 decides first, via a completely separate request/process -
        // simulated here with a raw UPDATE that bypasses this test's own
        // EntityManager/UnitOfWork entirely, exactly as a second PHP-FPM
        // worker handling admin 1's request would.
        $rows = $this->em->getConnection()->executeStatement('UPDATE refund_request SET status = ?, version = version + 1 WHERE id = ?', [
            RefundRequest::STATUS_APPROVED,
            $refundRequestId->toRfc4122(),
        ]);
        static::assertSame(1, $rows);

        // Admin 2's in-memory copy is now stale (it still thinks version 1);
        // declining it must fail loudly via Doctrine's optimistic lock check
        // rather than silently overwriting admin 1's decision.
        $copyForAdmin2->decline($admin2, 'Admin 2 declines');
        $this->expectException(OptimisticLockException::class);
        $this->em->flush();
    }
}
