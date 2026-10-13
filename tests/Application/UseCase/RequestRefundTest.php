<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Application\UseCase\RequestRefund;
use App\Entity\Booking;
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
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class RequestRefundTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private RequestRefund $requestRefund;

    private RefundRequestRepositoryInterface $refundRequestRepository;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var RequestRefund $requestRefund */
        $requestRefund = $container->get(RequestRefund::class);
        $this->requestRefund = $requestRefund;
        /** @var RefundRequestRepositoryInterface $refundRequestRepository */
        $refundRequestRepository = $container->get(RefundRequestRepositoryInterface::class);
        $this->refundRequestRepository = $refundRequestRepository;
    }

    public function testRequestingARefundRecordsAPendingRequestForTheFullPaidAmount(): void
    {
        $customer = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+3 days'))->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(120, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $booking = $this->persistBooking($customer, $payment, $lesson);
        $customerId = $customer->getId();
        static::assertNotNull($customerId);

        ($this->requestRefund)($booking->getId(), $lesson->getId(), $customerId, 'Zmiana planów');
        $this->em->flush();
        $this->em->clear();

        $reloadedPayment = $this->em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_REFUND_REQUESTED, $reloadedPayment->getStatus());
        static::assertTrue($reloadedPayment->isRefundRequestedViaUserPanel());

        $request = $this->refundRequestRepository->findPendingForPayment($reloadedPayment);
        static::assertInstanceOf(RefundRequest::class, $request);
        static::assertTrue($request->isPending());
        static::assertSame(12_000, $request->getRequestedAmountMinor());
        static::assertSame('PLN', $request->getCurrency());
        static::assertSame('Zmiana planów', $request->getRequestMessage());
        static::assertSame($customerId, $request->getRequestedBy()?->getId());
        static::assertTrue($request->getBooking()->getId()->equals($booking->getId()));
        static::assertTrue($request->getLesson()?->getId()->equals($lesson->getId()));
    }

    public function testAnAdminRequestWithinTheLast24HoursIsAllowedAndNotFlaggedAsUserPanel(): void
    {
        $customer = UserAssembler::new()->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+2 hours'))->assemble();
        $payment = PaymentAssembler::new()->withUser($customer)->withStatus(Payment::STATUS_PAID)->assemble();
        $booking = $this->persistBooking($customer, $payment, $lesson);
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->requestRefund)($booking->getId(), $lesson->getId(), $adminId, null);
        $this->em->flush();

        static::assertSame(Payment::STATUS_REFUND_REQUESTED, $payment->getStatus());
        static::assertFalse($payment->isRefundRequestedViaUserPanel());
        static::assertInstanceOf(RefundRequest::class, $this->refundRequestRepository->findPendingForPayment($payment));
    }

    public function testACustomerRequestWithin24HoursIsRejectedWithoutChangingAnything(): void
    {
        $customer = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+12 hours'))->assemble();
        $payment = PaymentAssembler::new()->withUser($customer)->withStatus(Payment::STATUS_PAID)->assemble();
        $booking = $this->persistBooking($customer, $payment, $lesson);
        $customerId = $customer->getId();
        static::assertNotNull($customerId);

        try {
            ($this->requestRefund)($booking->getId(), $lesson->getId(), $customerId, 'Za późno');
            static::fail('Expected a refund inside the 24h window to be rejected.');
        } catch (\RuntimeException $exception) {
            static::assertStringContainsString('24h', $exception->getMessage());
        }

        $this->em->flush();
        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
        static::assertSame(Booking::STATUS_ACTIVE, $booking->getStatus());
        static::assertNull($this->refundRequestRepository->findPendingForPayment($payment));
    }

    public function testABookingThatIsAlreadyCancelledCannotRequestARefund(): void
    {
        $customer = UserAssembler::new()->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+3 days'))->assemble();
        $payment = PaymentAssembler::new()->withUser($customer)->withStatus(Payment::STATUS_PAID)->assemble();
        $booking = $this->persistBooking($customer, $payment, $lesson, Booking::STATUS_CANCELLED);
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot request refund for this booking in its current state');

        ($this->requestRefund)($booking->getId(), $lesson->getId(), $adminId, null);
    }

    public function testAPaymentThatWasNeverPaidGetsNoRefundRequest(): void
    {
        $customer = UserAssembler::new()->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+3 days'))->assemble();
        $payment = PaymentAssembler::new()->withUser($customer)->withStatus(Payment::STATUS_PENDING)->assemble();
        $booking = $this->persistBooking($customer, $payment, $lesson);
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->requestRefund)($booking->getId(), $lesson->getId(), $adminId, null);
        $this->em->flush();

        static::assertSame(Payment::STATUS_PENDING, $payment->getStatus());
        static::assertNull($this->refundRequestRepository->findPendingForPayment($payment));
    }

    public function testAnUnknownRequestingUserIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User 999999999 not found');

        ($this->requestRefund)(new Ulid(), new Ulid(), 999_999_999, null);
    }

    public function testAnUnknownBookingIsALoggedNoOp(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->requestRefund)(new Ulid(), new Ulid(), $adminId, 'Brak rezerwacji');
        $this->em->flush();

        static::assertCount(0, $this->em->getRepository(RefundRequest::class)->findAll());
    }

    public function testALessonThatIsNotPartOfTheBookingIsALoggedNoOp(): void
    {
        $customer = UserAssembler::new()->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+3 days'))->assemble();
        $payment = PaymentAssembler::new()->withUser($customer)->withStatus(Payment::STATUS_PAID)->assemble();
        $booking = $this->persistBooking($customer, $payment, $lesson);
        $this->em->persist($admin);
        $this->em->flush();
        $adminId = $admin->getId();
        static::assertNotNull($adminId);

        ($this->requestRefund)($booking->getId(), new Ulid(), $adminId, null);
        $this->em->flush();

        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
        static::assertSame(Booking::STATUS_ACTIVE, $booking->getStatus());
        static::assertNull($this->refundRequestRepository->findPendingForPayment($payment));
    }

    private function persistBooking(
        \App\Entity\User $customer,
        Payment $payment,
        \App\Entity\Lesson $lesson,
        string $status = Booking::STATUS_ACTIVE,
    ): Booking {
        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus($status)
            ->assemble();

        foreach ([$customer, $lesson, $payment, $booking] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return $booking;
    }
}
