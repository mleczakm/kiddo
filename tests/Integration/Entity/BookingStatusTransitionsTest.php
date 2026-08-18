<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\AgeRange;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\User;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
class BookingStatusTransitionsTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private User $user;

    private Lesson $lesson;

    #[\Override]
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
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $this->lesson = new Lesson($metadata, Clock::get()->now()->modify('+1 day'));
        $this->entityManager->persist($this->lesson);

        $this->entityManager->flush();
    }

    public function testBookingStartsInPendingStatus(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertEquals(Booking::STATUS_PENDING, $booking->getStatus());
        static::assertTrue($booking->isPending());
    }

    public function testBookingCanBeConfirmedFromPending(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertTrue($booking->canBeConfirmed());
        $booking->confirm();
        $this->entityManager->flush();

        static::assertEquals(Booking::STATUS_ACTIVE, $booking->getStatus());
        static::assertTrue($booking->isConfirmed());
    }

    public function testBookingCanBeConfirmedFromWaitingApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->setStatus(Booking::STATUS_WAITING_APPROVAL);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertTrue($booking->canBeConfirmed());
        $booking->confirm();
        $this->entityManager->flush();

        static::assertEquals(Booking::STATUS_ACTIVE, $booking->getStatus());
    }

    public function testBookingCanBeCancelled(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertTrue($booking->canBeCancelled());
        $booking->cancel($this->user, 'Test reason');
        $this->entityManager->flush();

        static::assertEquals(Booking::STATUS_CANCELLED, $booking->getStatus());
        static::assertTrue($booking->isCancelled());
        static::assertSame('Test reason', $booking->getNotes());
    }

    public function testBookingCanBeCompleted(): void
    {
        // A booking can only be completed once its lesson has actually happened,
        // so this test needs a past-scheduled lesson rather than the shared
        // future-scheduled fixture from setUp().
        $pastMetadata = new LessonMetadata(
            title: 'Past Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            duration: 90,
            ageRange: new AgeRange(0, 10),
            category: 'Test',
        );
        $pastLesson = new Lesson($pastMetadata, Clock::get()->now()->modify('-1 day'));
        $this->entityManager->persist($pastLesson);

        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $pastLesson);
        $booking->confirm();
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertTrue($booking->canBeCompleted());
        $booking->complete();
        $this->entityManager->flush();

        static::assertEquals(Booking::STATUS_PAST, $booking->getStatus());
        static::assertTrue($booking->isPast());
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

        static::assertFalse($booking->canBeConfirmed());
    }

    public function testBookingCannotBeCancelledWhenPast(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'));
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->complete();
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertFalse($booking->canBeCancelled());
    }
}
