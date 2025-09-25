<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\CheckBookingsToMarkPast;
use App\Application\CommandHandler\CheckBookingsToMarkPastHandler;
use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class CheckBookingsToMarkPastHandlerTest extends KernelTestCase
{
    public function testTransitionsEligibleActiveBookingsToPast(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $past = $now->modify('-2 hours');
        $future = $now->modify('+2 hours');

        $user = UserAssembler::new()->assemble();

        // Booking A: active, all active lessons are in the past -> should become past
        $lessonPast = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($past)->assemble())
            ->assemble();
        $em->persist($lessonPast);
        $bookingA = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lessonPast)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $lessonPast->addBooking($bookingA);
        $em->persist($bookingA);

        // Booking B: active, has a future active lesson -> should remain active
        $lessonFuture = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($future)->assemble())
            ->assemble();
        $em->persist($lessonFuture);
        $bookingB = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lessonFuture)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $lessonFuture->addBooking($bookingB);
        $em->persist($bookingB);

        // Booking C: active, only lesson is future but cancelled -> should become past per spec
        $lessonFuture2 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($future)->assemble())
            ->assemble();
        $em->persist($lessonFuture2);
        $bookingC = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lessonFuture2)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $lessonFuture2->addBooking($bookingC);
        // cancel the only lesson on booking C
        $bookingC->cancelLesson((string) $lessonFuture2->getId(), 'cancelled for test');
        $em->persist($bookingC);
        $em->persist($user);

        $em->flush();

        // Invoke handler
        /** @var CheckBookingsToMarkPastHandler $handler */
        $handler = self::getContainer()->get(CheckBookingsToMarkPastHandler::class);
        $handler(new CheckBookingsToMarkPast());

        // Refresh entities from repository and assert statuses
        $savedA = $bookingRepository->find($bookingA->getId());
        $savedB = $bookingRepository->find($bookingB->getId());
        $savedC = $bookingRepository->find($bookingC->getId());

        self::assertNotNull($savedA);
        self::assertNotNull($savedB);
        self::assertNotNull($savedC);

        self::assertSame(Booking::STATUS_PAST, $savedA->getStatus(), 'Booking A should be marked as past');
        self::assertSame(Booking::STATUS_ACTIVE, $savedB->getStatus(), 'Booking B should remain active');
        self::assertSame(
            Booking::STATUS_PAST,
            $savedC->getStatus(),
            'Booking C with all lessons cancelled/past should be marked as past'
        );
    }
}
