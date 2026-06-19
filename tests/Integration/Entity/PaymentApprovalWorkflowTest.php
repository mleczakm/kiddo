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
class PaymentApprovalWorkflowTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private User $user;

    private User $adminUser;

    private Lesson $lesson;

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

    public function testOnlinePaymentDoesNotRequireApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::ONLINE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertFalse($payment->requiresApproval());
        $this->assertFalse($booking->requiresApproval());
    }

    public function testPayOnPlacePaymentRequiresApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertTrue($payment->requiresApproval());
        $this->assertTrue($booking->requiresApproval());
    }

    public function testBookingCanTransitionToWaitingApproval(): void
    {
        $payment = new Payment($this->user, Money::of(55, 'PLN'), PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->persist($payment);

        $booking = new Booking($this->user, $payment, $this->lesson);
        $booking->setStatus(Booking::STATUS_WAITING_APPROVAL);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertTrue($booking->isWaitingApproval());
        $this->assertEquals(Booking::STATUS_WAITING_APPROVAL, $booking->getStatus());
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

        $this->assertFalse($booking->isWaitingApproval());
        $this->assertTrue($booking->isConfirmed());
        $this->assertEquals($this->adminUser->getId(), $booking->getApprovedBy()->getId());
        $this->assertNotNull($booking->getApprovedAt());
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

        $this->assertFalse($payment->requiresApproval());

        $payment->setMethod(PaymentMethod::PAY_ON_PLACE);
        $this->entityManager->flush();

        $this->assertTrue($payment->requiresApproval());
    }
}
