<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\UserAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\LessonAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookingRepositoryTest extends KernelTestCase
{
    private BookingRepository $bookingRepository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->bookingRepository = self::getContainer()->get(BookingRepository::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testSaveBookingWithBasicData(): void
    {
        // Arrange: Create a user and booking with minimal data
        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

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

        $this->assertNotNull($savedBooking);
        $this->assertEquals($booking->getId(), $savedBooking->getId());
        $this->assertEquals(Booking::STATUS_PENDING, $savedBooking->getStatus());
        $this->assertEquals('Test booking notes', $savedBooking->getNotes());
        $this->assertEquals($user->getId(), $savedBooking->getUser()->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $savedBooking->getCreatedAt());
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

        $this->assertNotNull($savedBooking);
        $this->assertNotNull($savedBooking->getPayment());
        $this->assertEquals($payment->getId(), $savedBooking->getPayment()->getId());
        $this->assertEquals(Booking::STATUS_ACTIVE, $savedBooking->getStatus());
        $this->assertEquals('150.00', $savedBooking->getPayment()->getAmount()->getAmount()->__toString());
    }

    public function testSaveBookingWithLessons(): void
    {
        // Arrange: Create user, lessons, and booking
        $user = UserAssembler::new()
            ->withEmail('user-with-lessons@example.com')
            ->withName('User With Lessons')
            ->assemble();

        $lesson1 = LessonAssembler::new()
            ->withTitle('Art Workshop')
            ->withStatus('active')
            ->assemble();

        $lesson2 = LessonAssembler::new()
            ->withTitle('Music Class')
            ->withStatus('active')
            ->assemble();

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

        $this->assertNotNull($savedBooking);
        $this->assertCount(2, $savedBooking->getLessons());

        $lessonTitles = [];
        foreach ($savedBooking->getLessons() as $lesson) {
            $lessonTitles[] = $lesson->getMetadata()->title;
        }

        $this->assertContains('Art Workshop', $lessonTitles);
        $this->assertContains('Music Class', $lessonTitles);
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

        $lesson = LessonAssembler::new()
            ->withTitle('Complete Workshop')
            ->withStatus('active')
            ->assemble();

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

        $this->assertNotNull($savedBooking);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $savedBooking->getStatus());
        $this->assertEquals('Complete booking with all data', $savedBooking->getNotes());
        $this->assertEquals($createdAt->format('Y-m-d H:i:s'), $savedBooking->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals($user->getId(), $savedBooking->getUser()->getId());
        $this->assertEquals($payment->getId(), $savedBooking->getPayment()->getId());
        $this->assertCount(1, $savedBooking->getLessons());
        $this->assertEquals('Complete Workshop', $savedBooking->getLessons()->first()->getMetadata()->title);
    }

    public function testSaveBookingWithDifferentStatuses(): void
    {
        // Arrange: Create bookings with different statuses
        $user = UserAssembler::new()
            ->withEmail('status-test@example.com')
            ->withName('Status Test User')
            ->assemble();

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
            $this->assertNotNull($savedBooking);
            $this->assertEquals($originalBooking->getStatus(), $savedBooking->getStatus());
        }

        // Verify we can find bookings by status
        $activeBookings = $this->bookingRepository->findBy([
            'status' => Booking::STATUS_ACTIVE,
        ]);
        $this->assertCount(1, $activeBookings);
        $this->assertEquals(Booking::STATUS_ACTIVE, $activeBookings[0]->getStatus());
    }

    public function testSaveBookingAndRetrieveByUser(): void
    {
        // Arrange: Create multiple users with bookings
        $user1 = UserAssembler::new()
            ->withEmail('user1@example.com')
            ->withName('User One')
            ->assemble();

        $user2 = UserAssembler::new()
            ->withEmail('user2@example.com')
            ->withName('User Two')
            ->assemble();

        $booking1 = BookingAssembler::new()
            ->withUser($user1)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $booking2 = BookingAssembler::new()
            ->withUser($user1)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();

        $booking3 = BookingAssembler::new()
            ->withUser($user2)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

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

        $this->assertCount(2, $user1Bookings);
        $this->assertCount(1, $user2Bookings);

        // Verify the bookings belong to the correct users
        foreach ($user1Bookings as $booking) {
            $this->assertEquals($user1->getId(), $booking->getUser()->getId());
        }

        $this->assertEquals($user2->getId(), $user2Bookings[0]->getUser()->getId());
    }

    public function testUpdateBookingStatus(): void
    {
        // Arrange: Create and save a booking
        $user = UserAssembler::new()
            ->withEmail('update-test@example.com')
            ->withName('Update Test User')
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();

        $this->entityManager->persist($user);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Act: Update the booking status
        $booking->setStatus(Booking::STATUS_ACTIVE);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Assert: Verify the status was updated
        $updatedBooking = $this->bookingRepository->find($booking->getId());
        $this->assertNotNull($updatedBooking);
        $this->assertEquals(Booking::STATUS_ACTIVE, $updatedBooking->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedBooking->getUpdatedAt());
    }
}
