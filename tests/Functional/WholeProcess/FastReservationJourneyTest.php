<?php

declare(strict_types=1);

namespace App\Tests\Functional\WholeProcess;

use App\Application\Command\MatchPaymentForTransfer;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\WorkshopType;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Message\RefundLessonBooking;
use App\MessageHandler\RefundLessonBookingHandler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\LessonModal;
use App\UserInterface\Http\Component\ReservationDetailsModal;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * End-to-end characterization of the canonical fast-reservation journey
 * described in the commerce rollout plan:
 *
 *   workshop -> confirm -> booking -> payment code
 *   -> transfer email -> paid -> confirmed booking
 *   -> cancel and request refund -> admin approve
 *
 * This documents current behavior across the whole path (not just one
 * handler) so future refactoring has something concrete to preserve or
 * deliberately change.
 */
#[Group('functional')]
final class FastReservationJourneyTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testFastReservationJourneyFromBookingThroughPaymentToApprovedRefund(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var MessageBusInterface $messageBus */
        $messageBus = static::getContainer()->get(MessageBusInterface::class);

        $customer = UserAssembler::new()->withName('Anna Kowalska')->withPhone('501111111')->assemble();
        $admin = UserAssembler::new()->withName('Admin User')->withRoles('ROLE_ADMIN')->assemble();
        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Sensoplastyka')->assemble())
            // Scheduled well past both the payment window and the 24h refund
            // boundary, so every later step in this journey is unambiguous.
            ->withSchedule(new \DateTimeImmutable('2024-02-25 10:30:00'))
            ->assemble();
        $lesson->setSeries($series);

        $em->persist($customer);
        $em->persist($admin);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        // Step 1: open the workshop modal and confirm -> booking pending,
        // payment pending, a unique payment code is created.
        $client->loginUser($customer);
        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'termsAccepted' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );
        $component->call('processPayment');

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        static::assertSame('awaiting_payment', $lessonModal->paymentStatus);
        $paymentCode = $lessonModal->paymentCode;
        static::assertNotNull($paymentCode);

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = static::getContainer()->get(BookingRepository::class);
        $booking = $bookingRepository->findOneBy([
            'user' => $customer,
        ]);
        static::assertInstanceOf(Booking::class, $booking);
        static::assertSame(Booking::STATUS_PENDING, $booking->getStatus());

        $payment = $booking->getPayment();
        static::assertInstanceOf(Payment::class, $payment);
        static::assertSame(Payment::STATUS_PENDING, $payment->getStatus());
        static::assertSame($paymentCode, $payment->getPaymentCode()?->getCode());
        // The amount shown to the user must equal the amount actually persisted.
        static::assertEquals($payment->getAmount(), $lessonModal->getPaymentAmount());

        // Step 2: a transfer email arrives with the matching code -> payment
        // is paid and the booking is automatically confirmed.
        $transfer = TransferAssembler::new()
            ->withTitle(sprintf('Payment for order %s', $paymentCode))
            ->withAmount((string) $payment->getAmount()->getAmount())
            ->assemble();
        $em->persist($transfer);
        $em->flush();

        $messageBus->dispatch(new MatchPaymentForTransfer($transfer));

        // The sync bus runs messenger.middleware.doctrine_close_connection,
        // which closes the entity manager after handling. Reset it via the
        // registry (not just $em->clear()) so services resolved afterwards -
        // like the refund handler below - get a properly reconnected one
        // instead of stale/empty lazy associations.
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $paidPayment = $em->find(Payment::class, $payment->getId());
        $confirmedBooking = $em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Payment::class, $paidPayment);
        static::assertInstanceOf(Booking::class, $confirmedBooking);
        static::assertSame(Payment::STATUS_PAID, $paidPayment->getStatus());
        static::assertSame(Booking::STATUS_ACTIVE, $confirmedBooking->getStatus());

        // Step 3: the customer cancels and requests a refund. Today this is a
        // single operation (RefundLessonBooking) rather than the two
        // separate steps ("cancel" + "request refund") the target model
        // describes - it marks the specific lesson refunded within the
        // booking and moves the payment to refund_requested, but the
        // booking's overall status stays "active".
        $bookedLesson = $confirmedBooking->getLessons()->first();
        static::assertNotFalse($bookedLesson);
        // $customer was loaded through the entity manager instance that
        // resetManager() just replaced, so it's detached now - reload it
        // through the fresh one before handing it to the handler.
        $reloadedCustomer = $em->find(User::class, $customer->getId());
        static::assertInstanceOf(User::class, $reloadedCustomer);

        /** @var RefundLessonBookingHandler $refundHandler */
        $refundHandler = static::getContainer()->get(RefundLessonBookingHandler::class);
        $refundHandler(
            new RefundLessonBooking(
                $confirmedBooking->getId(),
                $bookedLesson->getId(),
                $reloadedCustomer,
                'Proszę o zwrot, plany się zmieniły.',
            ),
        );
        $em->flush();

        $em->clear();
        $refundRequestedPayment = $em->find(Payment::class, $payment->getId());
        $bookingAfterRefundRequest = $em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Payment::class, $refundRequestedPayment);
        static::assertInstanceOf(Booking::class, $bookingAfterRefundRequest);
        static::assertSame(Payment::STATUS_REFUND_REQUESTED, $refundRequestedPayment->getStatus());
        static::assertTrue($refundRequestedPayment->isRefundRequestedViaUserPanel());
        static::assertSame(
            Booking::STATUS_ACTIVE,
            $bookingAfterRefundRequest->getStatus(),
            'A refund request alone does not change the booking\'s overall status today',
        );
        static::assertNotNull(
            $bookingAfterRefundRequest->getLessonsMap()->getCancelledEntry((string) $bookedLesson->getId()),
            'The specific lesson is moved into the lesson map\'s cancelled bucket immediately on request, '
            . 'before any money has actually moved',
        );
        static::assertNull(
            $bookingAfterRefundRequest->getLessonsMap()->getCancellationReason((string) $bookedLesson->getId()),
            'refundLesson() moves the entry as a plain BookedLesson rather than a CancelledLesson, so the '
            . 'reason passed to RefundLessonBooking is silently dropped and not retrievable here',
        );

        // Step 4: an admin approves the refund -> payment moves to refunded.
        $client->loginUser($admin);
        $adminComponent = $this->createLiveComponent(name: ReservationDetailsModal::class, client: $client);
        $adminComponent->call('open', [
            'bookingId' => (string) $booking->getId(),
        ]);
        $adminComponent->set('paymentNote', 'Zwrot wykonany przelewem.');
        $adminComponent->call('changePaymentStatus', [
            'transition' => Payment::TRANSITION_REFUND,
        ]);

        $em->clear();
        $refundedPayment = $em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $refundedPayment);
        static::assertSame(Payment::STATUS_REFUNDED, $refundedPayment->getStatus());
        static::assertSame('Zwrot wykonany przelewem.', $refundedPayment->getStatusNote());
        static::assertSame($admin->getId(), $refundedPayment->getStatusChangedBy()?->getId());
    }
}
