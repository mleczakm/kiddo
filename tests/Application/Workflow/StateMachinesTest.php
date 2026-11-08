<?php

declare(strict_types=1);

namespace App\Tests\Application\Workflow;

use App\Application\Workflow\StateMachinesInterface;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Payment;
use App\Entity\RefundRequest;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\WaitlistEntry;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Lifecycles are declared once, in config/packages/workflow.yaml, and driven through
 * StateMachinesInterface. These tests pin the behaviour the former hand-rolled status
 * methods had (no-ops, idempotency, bookkeeping) so the migration stays behaviour-neutral.
 */
#[Group('functional')]
final class StateMachinesTest extends KernelTestCase
{
    private StateMachinesInterface $stateMachines;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $stateMachines = self::getContainer()->get(StateMachinesInterface::class);
        \assert($stateMachines instanceof StateMachinesInterface);
        $this->stateMachines = $stateMachines;
    }

    public function testLessonCanBeCancelledAndReopened(): void
    {
        $lesson = new Lesson($this->createStub(LessonMetadata::class), new \DateTimeImmutable('+3 days'));

        static::assertTrue($lesson->isActive());
        $this->stateMachines->apply($lesson, 'cancel');
        static::assertTrue($lesson->isCancelled());
        static::assertFalse($this->stateMachines->can($lesson, 'cancel'));
        $this->stateMachines->apply($lesson, 'reactivate');
        static::assertTrue($lesson->isActive());
    }

    public function testWaitlistEntryOfferStampsHoldWindowAndClaimClosesIt(): void
    {
        $entry = $this->waitlistEntry();
        $now = new \DateTimeImmutable('2026-01-01 10:00:00');

        $this->stateMachines->apply($entry, 'offer', [
            'now' => $now,
        ]);

        static::assertSame(WaitlistEntry::STATUS_OFFERED, $entry->getStatus());
        static::assertEquals($now->modify('+60 minutes'), $entry->getOfferExpiresAt());
        // Offering twice is not enabled - callers use can()/applyIfPossible() for the old no-op.
        static::assertFalse($this->stateMachines->applyIfPossible($entry, 'offer'));

        $this->stateMachines->apply($entry, 'claim', [
            'now' => $now,
        ]);
        static::assertSame(WaitlistEntry::STATUS_CLAIMED, $entry->getStatus());
        static::assertFalse($entry->isActive());
        static::assertFalse($this->stateMachines->can($entry, 'cancel'));
    }

    public function testWaitlistEntryOnlyExpiresWhileOffered(): void
    {
        $entry = $this->waitlistEntry();

        static::assertFalse($this->stateMachines->can($entry, 'expire'));
        $this->stateMachines->apply($entry, 'offer');
        static::assertTrue($this->stateMachines->applyIfPossible($entry, 'expire'));
        static::assertSame(WaitlistEntry::STATUS_EXPIRED, $entry->getStatus());
    }

    public function testRefundRequestCanBeDecidedOnlyOnce(): void
    {
        $request = new RefundRequest(
            $this->createStub(Payment::class),
            $this->createStub(Booking::class),
            null,
            Money::of(10, 'PLN'),
            null,
            null,
        );

        static::assertTrue($this->stateMachines->can($request, 'approve'));
        $this->stateMachines->apply($request, 'decline');
        $request->recordDecision($this->createStub(User::class), '  nope  ');

        static::assertSame(RefundRequest::STATUS_DECLINED, $request->getStatus());
        static::assertFalse($this->stateMachines->can($request, 'approve'));
        static::assertFalse($this->stateMachines->can($request, 'decline'));
    }

    public function testSubscriptionPausesResumesAndEndsOnlyFromLiveStates(): void
    {
        $subscription = new Subscription(
            $this->createStub(User::class),
            $this->createStub(\App\Entity\Series::class),
            100,
        );

        static::assertFalse($this->stateMachines->can($subscription, 'resume'));
        $this->stateMachines->apply($subscription, 'pause');
        static::assertFalse($subscription->isActive());
        $this->stateMachines->apply($subscription, 'resume');
        static::assertTrue($subscription->isActive());
        $this->stateMachines->apply($subscription, 'end');
        static::assertSame(Subscription::STATUS_ENDED, $subscription->getStatus());
        static::assertNotNull($subscription->getEndsAt());
        static::assertFalse($this->stateMachines->can($subscription, 'pause'));
        static::assertFalse($this->stateMachines->can($subscription, 'end'));
    }

    private function waitlistEntry(): WaitlistEntry
    {
        return new WaitlistEntry(
            $this->createStub(Lesson::class),
            $this->createStub(User::class),
            'parent@example.com',
            'Parent',
        );
    }
}
