<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\AgeRange;
use App\Entity\User;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[\PHPUnit\Framework\Attributes\Group('functional')]
class BookingStatusTransitionsTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private User $user;

    private Lesson $lesson;

    protected function setUp(): void
    {
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        // Create test user
        $this->user = new User('parent@test.com', 'Parent User');
        $this->entityManager->persist($this->user);

        // Create test lesson
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            schedule: Clock::get()->now()->modify('+1 day'),
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $this->lesson = new Lesson($metadata);
        $this->entityManager->persist($this->lesson);

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testBookingStartsInPendingStatus(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertEquals(Booking::STATUS_PENDING, $booking->getStatus());
        $this->assertTrue($booking->isPending());
    }

    public function testBookingCanBeConfirmedFromPending(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertTrue($booking->canBeConfirmed());
        $booking->confirm();
        $this->entityManager->flush();

        $this->assertEquals(Booking::STATUS_ACTIVE, $booking->getStatus());
        $this->assertTrue($booking->isConfirmed());
    }

    public function testBookingCanBeConfirmedFromWaitingApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->setStatus(Booking::STATUS_WAITING_APPROVAL);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertTrue($booking->canBeConfirmed());
        $booking->confirm();
        $this->entityManager->flush();

        $this->assertEquals(Booking::STATUS_ACTIVE, $booking->getStatus());
    }

    public function testBookingCanBeCancelled(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertTrue($booking->canBeCancelled());
        $booking->cancel($this->user, 'Test reason');
        $this->entityManager->flush();

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->getStatus());
        $this->assertTrue($booking->isCancelled());
        $this->assertEquals('Test reason', $booking->getNotes());
    }

    public function testBookingCanBeCompleted(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->confirm();
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertTrue($booking->canBeCompleted());
        $booking->complete();
        $this->entityManager->flush();

        $this->assertEquals(Booking::STATUS_PAST, $booking->getStatus());
        $this->assertTrue($booking->isPast());
    }

    public function testInvalidStatusThrowsException(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $booking->setStatus('invalid_status');
    }

    public function testBookingCannotBeConfirmedWhenCancelled(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->cancel($this->user);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertFalse($booking->canBeConfirmed());
    }

    public function testBookingCannotBeCancelledWhenPast(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->complete();
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertFalse($booking->canBeCancelled());
    }
}
