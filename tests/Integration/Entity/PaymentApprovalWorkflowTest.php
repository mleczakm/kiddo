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
class PaymentApprovalWorkflowTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private User $user;

    private User $adminUser;

    private Lesson $lesson;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        // Create test users
        $this->user = new User('parent@test.com', 'Parent User');
        $this->entityManager->persist($this->user);

        $this->adminUser = new User('admin@test.com', 'Admin User');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($this->adminUser);

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

    public function testOnlinePaymentDoesNotRequireApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::ONLINE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertFalse($payment->requiresApproval());
        static::assertFalse($booking->requiresApproval());
    }

    public function testPayOnPlacePaymentRequiresApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertTrue($payment->requiresApproval());
        static::assertTrue($booking->requiresApproval());
    }

    public function testBookingCanTransitionToWaitingApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->setStatus(Booking::STATUS_WAITING_APPROVAL);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        static::assertTrue($booking->isWaitingApproval());
        static::assertEquals(Booking::STATUS_WAITING_APPROVAL, $booking->getStatus());
    }

    public function testBookingCanBeApproved(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->setStatus(Booking::STATUS_WAITING_APPROVAL);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Approve the booking
        $booking->approve($this->adminUser);
        $this->entityManager->flush();

        static::assertFalse($booking->isWaitingApproval());
        static::assertTrue($booking->isConfirmed());
        $approvedBy = $booking->getApprovedBy();
        static::assertNotNull($approvedBy);
        static::assertEquals($this->adminUser->getId(), $approvedBy->getId());
        static::assertNotNull($booking->getApprovedAt());
    }

    public function testCannotApproveBookingNotWaitingApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::ONLINE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->setStatus(Booking::STATUS_ACTIVE);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->expectException(\LogicException::class);
        $booking->approve($this->adminUser);
    }

    public function testPaymentMethodCanBeChanged(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::ONLINE);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        static::assertFalse($payment->requiresApproval());

        $payment->setMethod(PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->flush();

        static::assertTrue($payment->requiresApproval());
    }
}
