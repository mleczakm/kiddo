<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Booking;
use App\Entity\DTO\RescheduledLesson;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class BookingPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testBookingLessonMapPersistsActivePastAndRescheduled(): void
    {
        $now = Clock::get()->now();

        // Create lessons: one in the past, two in the future
        $pastLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($now->modify('-2 days'))->assemble())
            ->assemble();
        $futureA = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($now->modify('+2 days'))->assemble())
            ->assemble();
        $futureB = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($now->modify('+3 days'))->assemble())
            ->assemble();

        $user = UserAssembler::new()->withEmail('booker@example.com')->withName('Booker')->assemble();

        $this->em->persist($pastLesson);
        $this->em->persist($futureA);
        $this->em->persist($futureB);
        $this->em->persist($user);
        $this->em->flush();

        // Create booking with all three lessons
        $booking = new Booking($user, null, $pastLesson, $futureA, $futureB);
        $booking->setNotes('initial');

        // Confirm booking to classify as active for future lessons
        $ref = new \ReflectionClass($booking);
        $statusProp = $ref->getProperty('status');
        $statusProp->setValue($booking, Booking::STATUS_ACTIVE);

        $this->em->persist($booking);
        $this->em->flush();
        $this->em->clear();

        // Reload and assert map categorization
        /** @var Booking $reloaded */
        $reloaded = $this->em->getRepository(Booking::class)->find((string) $booking->getId());

        $map = $reloaded->getLessonsMap();
        // Lessons map should contain all three
        self::assertSame(3, $map->count());
        // Active should include the two future lessons
        self::assertTrue($map->active->hasKey($futureA->getId()));
        self::assertTrue($map->active->hasKey($futureB->getId()));
        // Past should include the past lesson
        self::assertTrue($map->past->hasKey($pastLesson->getId()));

        // Now reschedule futureA to a new future lesson
        $newFuture = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($now->modify('+5 days'))->assemble())
            ->assemble();
        $this->em->persist($newFuture);
        $this->em->flush();

        // Need the managed booking entity
        /** @var Booking $managed */
        $managed = $this->em->getRepository(Booking::class)->find((string) $booking->getId());
        $managed->rescheduleLesson($futureA, $newFuture, $user);
        $this->em->flush();
        $this->em->clear();

        /** @var Booking $reloaded2 */
        $reloaded2 = $this->em->getRepository(Booking::class)->find((string) $booking->getId());
        $map2 = $reloaded2->getLessonsMap();

        // New lesson should be in lessons and active
        self::assertTrue($map2->lessons->hasKey($newFuture->getId()));
        self::assertTrue($map2->active->hasKey($newFuture->getId()));
        // Original rescheduled-from lesson should be in cancelled with RescheduledLesson payload
        self::assertTrue($map2->cancelled->hasKey($futureA->getId()));
        $cancelledEntry = $map2->cancelled->get($futureA->getId());
        self::assertInstanceOf(RescheduledLesson::class, $cancelledEntry);
        self::assertSame((string) $newFuture->getId(), (string) $cancelledEntry->lessonId);
        // Past lesson remains in past
        self::assertTrue($map2->past->hasKey($pastLesson->getId()));
        // Active should still include futureB and newFuture
        self::assertTrue($map2->active->hasKey($futureB->getId()));
    }
}
