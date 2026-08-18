<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\CheckExpiredBookings;
use App\Application\CommandHandler\CheckExpiredBookingsHandler;
use App\Entity\ActivityType;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Repository\ActivityLogRepository;
use App\Repository\BookingRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class CheckExpiredBookingsHandlerTest extends KernelTestCase
{
    public function testCancelsExpiredUnpaidBookingsAndRecordsAnAutoCancellationEvent(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        /** @var ActivityLogRepository $activityLogRepository */
        $activityLogRepository = self::getContainer()->get(ActivityLogRepository::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expirationTime = $now->modify('-60 minutes');
        $longAgo = $now->modify('-2 hours');

        $user = UserAssembler::new()->assemble();

        // Booking A: pending, unpaid, created well before the expiration
        // window -> should be auto-cancelled and logged.
        $lessonA = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($now->modify('+1 day'))
            ->assemble();
        $em->persist($lessonA);
        $paymentA = PaymentAssembler::new()->withUser($user)->withStatus(Payment::STATUS_PENDING)->assemble();
        $em->persist($paymentA);
        $bookingA = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($paymentA)
            ->withLessons($lessonA)
            ->withStatus(Booking::STATUS_PENDING)
            ->withCreatedAt($longAgo)
            ->assemble();
        $lessonA->addBooking($bookingA);
        $em->persist($bookingA);

        // Booking B: pending, but already paid, created before the
        // expiration window -> must remain untouched.
        $lessonB = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($now->modify('+1 day'))
            ->assemble();
        $em->persist($lessonB);
        $paymentB = PaymentAssembler::new()->withUser($user)->withStatus(Payment::STATUS_PAID)->assemble();
        $em->persist($paymentB);
        $bookingB = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($paymentB)
            ->withLessons($lessonB)
            ->withStatus(Booking::STATUS_PENDING)
            ->withCreatedAt($longAgo)
            ->assemble();
        $lessonB->addBooking($bookingB);
        $em->persist($bookingB);

        $em->persist($user);
        $em->flush();

        /** @var CheckExpiredBookingsHandler $handler */
        $handler = self::getContainer()->get(CheckExpiredBookingsHandler::class);
        $handler(new CheckExpiredBookings($expirationTime));

        $em->clear();

        $savedA = $bookingRepository->find($bookingA->getId());
        $savedB = $bookingRepository->find($bookingB->getId());

        static::assertNotNull($savedA);
        static::assertNotNull($savedB);

        static::assertSame(Booking::STATUS_CANCELLED, $savedA->getStatus());
        static::assertNull(
            $savedA->getLessonsMap()->getCancelledByUserId((string) $lessonA->getId()),
            'Automatic cancellation should not attribute the lesson to any user',
        );
        static::assertNotNull($savedA->getLessonsMap()->getCancellationReason((string) $lessonA->getId()));

        static::assertSame(Booking::STATUS_PENDING, $savedB->getStatus(), 'Paid booking must not be auto-cancelled');

        $eventsForA = $activityLogRepository->findByBookingId((string) $bookingA->getId());
        static::assertCount(1, $eventsForA);
        static::assertSame(ActivityType::BOOKING_AUTO_CANCELLED, $eventsForA[0]->getType());

        $eventsForB = $activityLogRepository->findByBookingId((string) $bookingB->getId());
        static::assertCount(0, $eventsForB);
    }
}
