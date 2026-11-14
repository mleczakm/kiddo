<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Repository;

use App\Entity\Booking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class BookingLookupRepositoryTest extends BookingRepositoryTestCase
{
    public function testFindForUserAndLessonReturnsMatchingBookings(): void
    {
        $user = UserAssembler::new()->assemble();
        $otherUser = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $otherLesson = LessonAssembler::new()->assemble();

        $matching = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();
        $cancelled = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_CANCELLED)
            ->assemble();
        $otherUsersBooking = BookingAssembler::new()->withUser($otherUser)->withLessons($lesson)->assemble();
        $otherLessonBooking = BookingAssembler::new()->withUser($user)->withLessons($otherLesson)->assemble();

        $this->entityManager->persist($user);
        $this->entityManager->persist($otherUser);
        $this->entityManager->persist($lesson);
        $this->entityManager->persist($otherLesson);
        $this->entityManager->persist($matching);
        $this->entityManager->persist($cancelled);
        $this->entityManager->persist($otherUsersBooking);
        $this->entityManager->persist($otherLessonBooking);
        $this->entityManager->flush();

        $result = $this->bookingRepository->findForUserAndLesson($user, $lesson);

        static::assertCount(1, $result);
        static::assertTrue($result[0]->getId()->equals($matching->getId()));
    }

    public function testFindForUserAndLessonsReturnsMatchingBookings(): void
    {
        $user = UserAssembler::new()->assemble();
        $lessonA = LessonAssembler::new()->assemble();
        $lessonB = LessonAssembler::new()->assemble();
        $lessonC = LessonAssembler::new()->assemble();

        $bookingA = BookingAssembler::new()->withUser($user)->withLessons($lessonA)->assemble();
        $bookingB = BookingAssembler::new()->withUser($user)->withLessons($lessonB)->assemble();
        $bookingC = BookingAssembler::new()->withUser($user)->withLessons($lessonC)->assemble();

        $this->entityManager->persist($user);
        $this->entityManager->persist($lessonA);
        $this->entityManager->persist($lessonB);
        $this->entityManager->persist($lessonC);
        $this->entityManager->persist($bookingA);
        $this->entityManager->persist($bookingB);
        $this->entityManager->persist($bookingC);
        $this->entityManager->flush();

        $result = $this->bookingRepository->findForUserAndLessons($user, [$lessonA, $lessonB]);

        static::assertCount(2, $result);
        $ids = array_map(static fn(Booking $b) => (string) $b->getId(), $result);
        static::assertContains((string) $bookingA->getId(), $ids);
        static::assertContains((string) $bookingB->getId(), $ids);
        static::assertNotContains((string) $bookingC->getId(), $ids);
    }

    public function testFindForUserAndLessonsReturnsEmptyForEmptyIds(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        static::assertSame([], $this->bookingRepository->findForUserAndLessons($user, []));
    }
}
