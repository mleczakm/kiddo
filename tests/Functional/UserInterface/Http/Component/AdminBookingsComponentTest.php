<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminBookingsComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AdminBookingsComponentTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private AdminBookingsComponent $component;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->component = new AdminBookingsComponent(
            self::getContainer()->get('App\Repository\BookingRepository')
        );
    }

    public function testGetAllBookingsReturnsEmptyArrayWhenNoBookings(): void
    {
        $result = $this->component->getAllBookings();

        $this->assertEmpty($result);
    }

    public function testGetAllBookingsReturnsConfirmedBookings(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $lesson = LessonAssembler::new()
            ->withTitle('Test Workshop')
            ->withSchedule(new \DateTimeImmutable('+1 week'))
            ->assemble();
        $this->entityManager->persist($lesson);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $this->entityManager->persist($payment);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->withPayment($payment)
            ->assemble();
        $booking->addLesson($lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Act
        $result = $this->component->getAllBookings();

        // Assert
        $this->assertCount(1, $result);
        $item = $result[0];
        $this->assertSame($booking, $item['booking']);
        $this->assertEquals(1, $item['totalLessons']);
        $this->assertEquals(0, $item['completedLessons']);
        $this->assertEquals(1, $item['remainingLessons']);
        $this->assertFalse($item['isCarnet']);
    }

    public function testGetAllBookingsWithActiveFilter(): void
    {
        // Arrange
        $user1 = UserAssembler::new()->assemble();
        $user2 = UserAssembler::new()->assemble();
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);

        $activeBooking = BookingAssembler::new()
            ->withUser($user1)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();

        $cancelledBooking = BookingAssembler::new()
            ->withUser($user2)
            ->withStatus(Booking::STATUS_CANCELLED)
            ->assemble();

        $this->entityManager->persist($activeBooking);
        $this->entityManager->persist($cancelledBooking);
        $this->entityManager->flush();

        // Act
        $this->component->filter = 'active';
        $result = $this->component->getAllBookings();

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame($activeBooking, $result[0]['booking']);
    }

    public function testGetAllBookingsWithCancelledFilter(): void
    {
        // Arrange
        $user1 = UserAssembler::new()->assemble();
        $user2 = UserAssembler::new()->assemble();
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);

        $activeBooking = BookingAssembler::new()
            ->withUser($user1)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();

        $cancelledBooking = BookingAssembler::new()
            ->withUser($user2)
            ->withStatus(Booking::STATUS_CANCELLED)
            ->assemble();

        $this->entityManager->persist($activeBooking);
        $this->entityManager->persist($cancelledBooking);
        $this->entityManager->flush();

        // Act
        $this->component->filter = 'cancelled';
        $result = $this->component->getAllBookings();

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame($cancelledBooking, $result[0]['booking']);
    }

    public function testGetAllBookingsWithSearchFilter(): void
    {
        // Arrange
        $user1 = UserAssembler::new()
            ->withName('John Doe')
            ->withEmail('john@example.com')
            ->assemble();

        $user2 = UserAssembler::new()
            ->withName('Jane Smith')
            ->withEmail('jane@example.com')
            ->assemble();

        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);

        $booking1 = BookingAssembler::new()
            ->withUser($user1)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();

        $booking2 = BookingAssembler::new()
            ->withUser($user2)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();

        $this->entityManager->persist($booking1);
        $this->entityManager->persist($booking2);
        $this->entityManager->flush();

        // Act
        $this->component->search = 'John';
        $result = $this->component->getAllBookings();

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame($booking1, $result[0]['booking']);
    }

    public function testGetAllBookingsCalculatesProgressCorrectly(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $pastLesson = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('-1 week'))
            ->assemble();

        $futureLesson = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('+1 week'))
            ->assemble();

        $this->entityManager->persist($pastLesson);
        $this->entityManager->persist($futureLesson);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();

        $booking->addLesson($pastLesson);
        $booking->addLesson($futureLesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Act
        $result = $this->component->getAllBookings();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(2, $result[0]['totalLessons']);
        $this->assertEquals(1, $result[0]['completedLessons']);
        $this->assertEquals(1, $result[0]['remainingLessons']);
        $this->assertEquals(50.0, $result[0]['progress']);
    }

    public function testGetAllBookingsIdentifiesUpcomingLessons(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $pastLesson = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('-1 week'))
            ->assemble();

        $futureLesson1 = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('+1 week'))
            ->assemble();

        $futureLesson2 = LessonAssembler::new()
            ->withSchedule(new \DateTimeImmutable('+2 weeks'))
            ->assemble();

        $this->entityManager->persist($pastLesson);
        $this->entityManager->persist($futureLesson1);
        $this->entityManager->persist($futureLesson2);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();

        $booking->addLesson($pastLesson);
        $booking->addLesson($futureLesson1);
        $booking->addLesson($futureLesson2);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Act
        $result = $this->component->getAllBookings();

        // Assert
        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['upcomingLessons']);
    }

    public function testGetFilterCountsReturnsCorrectCounts(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $confirmedBooking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();

        $completedBooking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_COMPLETED)
            ->assemble();

        $cancelledBooking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_CANCELLED)
            ->assemble();

        $this->entityManager->persist($confirmedBooking);
        $this->entityManager->persist($completedBooking);
        $this->entityManager->persist($cancelledBooking);
        $this->entityManager->flush();

        // Act
        $result = $this->component->getFilterCounts();

        // Assert
        $this->assertEquals(3, $result['all']);
        $this->assertEquals(1, $result['active']);
        $this->assertEquals(1, $result['completed']);
        $this->assertEquals(1, $result['cancelled']);
    }

    public function testFilterPropertyIsWritable(): void
    {
        $this->component->filter = 'active';
        $this->assertEquals('active', $this->component->filter);

        $this->component->filter = 'cancelled';
        $this->assertEquals('cancelled', $this->component->filter);
    }

    public function testSearchPropertyIsWritable(): void
    {
        $this->component->search = 'test search';
        $this->assertEquals('test search', $this->component->search);

        $this->component->search = null;
        $this->assertNull($this->component->search);
    }

    public function testGetAllBookingsLimitsResults(): void
    {
        // Arrange - Create more than 50 bookings to test limit
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        for ($i = 0; $i < 60; $i++) {
            $booking = BookingAssembler::new()
                ->withUser($user)
                ->withStatus(Booking::STATUS_CONFIRMED)
                ->assemble();
            $this->entityManager->persist($booking);
        }
        $this->entityManager->flush();

        // Act
        $result = $this->component->getAllBookings();

        // Assert - Should be limited to 50 results
        $this->assertCount(50, $result);
    }

    public function testGetAllBookingsOrdersByCreatedAtDesc(): void
    {
        // Arrange
        $user = UserAssembler::new()->assemble();
        $this->entityManager->persist($user);

        $olderBooking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();
        $this->entityManager->persist($olderBooking);
        $this->entityManager->flush();

        // Wait a moment to ensure different timestamps
        usleep(1000);

        $newerBooking = BookingAssembler::new()
            ->withUser($user)
            ->withStatus(Booking::STATUS_CONFIRMED)
            ->assemble();
        $this->entityManager->persist($newerBooking);
        $this->entityManager->flush();

        // Act
        $result = $this->component->getAllBookings();

        // Assert - Should be ordered by createdAt DESC (newest first)
        $this->assertCount(2, $result);
        $this->assertSame($newerBooking, $result[0]['booking']);
        $this->assertSame($olderBooking, $result[1]['booking']);
    }
}
