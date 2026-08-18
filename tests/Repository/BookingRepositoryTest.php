<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Repository\BookingRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class BookingRepositoryTest extends KernelTestCase
{
    private BookingRepository $bookingRepository;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->bookingRepository = self::getContainer()->get(BookingRepository::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testSaveBookingWithBasicData(): void
    {
        // Arrange: Create a user and booking with minimal data
        $user = UserAssembler::new()->withEmail('test@example.com')->withName('Test User')->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_PENDING)
            ->withNotes('Test booking notes')
            ->assemble();

        // Act: Save the booking
        $this->entityManager->persist($user);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify the booking was saved correctly
        $savedBooking = $this->bookingRepository->find($booking->getId());

        static::assertNotNull($savedBooking);
        static::assertEquals($booking->getId(), $savedBooking->getId());
        static::assertEquals(Booking::STATUS_PENDING, $savedBooking->getStatus());
        static::assertSame('Test booking notes', $savedBooking->getNotes());
        static::assertEquals($user->getId(), $savedBooking->getUser()->getId());
        static::assertInstanceOf(\DateTimeImmutable::class, $savedBooking->getCreatedAt());
    }

    public function testSaveBookingWithPayment(): void
    {
        // Arrange: Create a user, payment, and booking
        $user = UserAssembler::new()
            ->withEmail('user-with-payment@example.com')
            ->withName('User With Payment')
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('150.00', 'PLN'))
            ->withStatus('paid')
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        // Act: Save the booking with payment
        $this->entityManager->persist($user);
        $this->entityManager->persist($payment);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify the booking and payment were saved correctly
        $savedBooking = $this->bookingRepository->find($booking->getId());

        static::assertNotNull($savedBooking);
        static::assertNotNull($savedBooking->getPayment());
        static::assertEquals($payment->getId(), $savedBooking->getPayment()->getId());
        static::assertEquals(Booking::STATUS_ACTIVE, $savedBooking->getStatus());
        static::assertSame('150.00', $savedBooking->getPayment()->getAmount()->getAmount()->__toString());
    }

    public function testSaveBookingWithLessons(): void
    {
        // Arrange: Create user, lessons, and booking
        $user = UserAssembler::new()
            ->withEmail('user-with-lessons@example.com')
            ->withName('User With Lessons')
            ->assemble();

        $lesson1 = LessonAssembler::new()->withTitle('Art Workshop')->withStatus('active')->assemble();

        $lesson2 = LessonAssembler::new()->withTitle('Music Class')->withStatus('active')->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson1, $lesson2)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        // Act: Save the booking with lessons
        $this->entityManager->persist($user);
        $this->entityManager->persist($lesson1);
        $this->entityManager->persist($lesson2);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify the booking and lessons were saved correctly
        $savedBooking = $this->bookingRepository->find($booking->getId());

        static::assertNotNull($savedBooking);
        static::assertCount(2, $savedBooking->getLessons());

        $lessonTitles = [];
        foreach ($savedBooking->getLessons() as $lesson) {
            $lessonTitles[] = $lesson->getMetadata()->title;
        }

        static::assertContains('Art Workshop', $lessonTitles);
        static::assertContains('Music Class', $lessonTitles);
    }

    public function testSaveCompleteBookingWithAllData(): void
    {
        // Arrange: Create a complete booking with all data
        $user = UserAssembler::new()
            ->withEmail('complete-booking@example.com')
            ->withName('Complete Booking User')
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('250.00', 'PLN'))
            ->withStatus('paid')
            ->assemble();

        $lesson = LessonAssembler::new()->withTitle('Complete Workshop')->withStatus('active')->assemble();

        $createdAt = new \DateTimeImmutable('2024-01-15 10:30:00');

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->withNotes('Complete booking with all data')
            ->withCreatedAt($createdAt)
            ->assemble();

        // Act: Save the complete booking
        $this->entityManager->persist($user);
        $this->entityManager->persist($payment);
        $this->entityManager->persist($lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify all data was saved correctly
        $savedBooking = $this->bookingRepository->find($booking->getId());

        static::assertNotNull($savedBooking);
        static::assertEquals(Booking::STATUS_CONFIRMED, $savedBooking->getStatus());
        static::assertSame('Complete booking with all data', $savedBooking->getNotes());
        static::assertEquals($createdAt->format('Y-m-d H:i:s'), $savedBooking->getCreatedAt()->format('Y-m-d H:i:s'));
        static::assertEquals($user->getId(), $savedBooking->getUser()->getId());

        $savedPayment = $savedBooking->getPayment();
        static::assertNotNull($savedPayment);
        static::assertEquals($payment->getId(), $savedPayment->getId());
        static::assertCount(1, $savedBooking->getLessons());
        /** @var Lesson $firstLesson */
        $firstLesson = $savedBooking->getLessons()->first();
        static::assertNotFalse($firstLesson);
        static::assertSame('Complete Workshop', $firstLesson->getMetadata()->title);
    }

    public function testSaveBookingWithDifferentStatuses(): void
    {
        // Arrange: Create bookings with different statuses
        $user = UserAssembler::new()->withEmail('status-test@example.com')->withName('Status Test User')->assemble();

        $statuses = [
            Booking::STATUS_PENDING,
            Booking::STATUS_ACTIVE,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_PAST,
        ];

        $bookings = [];
        foreach ($statuses as $status) {
            $booking = BookingAssembler::new()
                ->withUser($user)
                ->withStatus($status)
                ->withNotes("Booking with status: {$status}")
                ->assemble();
            $bookings[] = $booking;
        }

        // Act: Save all bookings
        $this->entityManager->persist($user);
        foreach ($bookings as $booking) {
            $this->entityManager->persist($booking);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify all bookings were saved with correct statuses
        foreach ($bookings as $originalBooking) {
            $savedBooking = $this->bookingRepository->find($originalBooking->getId());
            static::assertNotNull($savedBooking);
            static::assertEquals($originalBooking->getStatus(), $savedBooking->getStatus());
        }

        // Verify we can find bookings by status
        $activeBookings = $this->bookingRepository->findBy([
            'status' => Booking::STATUS_ACTIVE,
        ]);
        static::assertCount(1, $activeBookings);
        static::assertEquals(Booking::STATUS_ACTIVE, $activeBookings[0]->getStatus());
    }

    public function testSaveBookingAndRetrieveByUser(): void
    {
        // Arrange: Create multiple users with bookings
        $user1 = UserAssembler::new()->withEmail('user1@example.com')->withName('User One')->assemble();

        $user2 = UserAssembler::new()->withEmail('user2@example.com')->withName('User Two')->assemble();

        $booking1 = BookingAssembler::new()->withUser($user1)->withStatus(Booking::STATUS_ACTIVE)->assemble();

        $booking2 = BookingAssembler::new()->withUser($user1)->withStatus(Booking::STATUS_PENDING)->assemble();

        $booking3 = BookingAssembler::new()->withUser($user2)->withStatus(Booking::STATUS_ACTIVE)->assemble();

        // Act: Save all users and bookings
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->persist($booking1);
        $this->entityManager->persist($booking2);
        $this->entityManager->persist($booking3);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify we can retrieve bookings by user
        $user1Bookings = $this->bookingRepository->findBy([
            'user' => $user1->getId(),
        ]);
        $user2Bookings = $this->bookingRepository->findBy([
            'user' => $user2->getId(),
        ]);

        static::assertCount(2, $user1Bookings);
        static::assertCount(1, $user2Bookings);

        // Verify the bookings belong to the correct users
        foreach ($user1Bookings as $booking) {
            static::assertEquals($user1->getId(), $booking->getUser()->getId());
        }

        static::assertEquals($user2->getId(), $user2Bookings[0]->getUser()->getId());
    }

    public function testUpdateBookingStatus(): void
    {
        // Arrange: Create and save a booking
        $user = UserAssembler::new()->withEmail('update-test@example.com')->withName('Update Test User')->assemble();

        $booking = BookingAssembler::new()->withUser($user)->withStatus(Booking::STATUS_PENDING)->assemble();

        $this->entityManager->persist($user);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Act: Update the booking status
        $booking->setStatus(Booking::STATUS_ACTIVE);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify the status was updated
        $updatedBooking = $this->bookingRepository->find($booking->getId());
        static::assertNotNull($updatedBooking);
        static::assertEquals(Booking::STATUS_ACTIVE, $updatedBooking->getStatus());
        static::assertInstanceOf(\DateTimeImmutable::class, $updatedBooking->getUpdatedAt());
    }

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
