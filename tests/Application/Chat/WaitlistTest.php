<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Command\ExpireWaitlistOffers;
use App\Application\Command\OfferWaitlistSeats;
use App\Application\Repository\WaitlistEntryRepositoryInterface;
use App\Entity\Booking;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\WaitlistEntry;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class WaitlistTest extends KernelTestCase
{
    use InteractsWithMailer;

    private EntityManagerInterface $em;

    private ChatToolRegistry $registry;

    private WaitlistEntryRepositoryInterface $waitlist;

    private MessageBusInterface $bus;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $registry = self::getContainer()->get(ChatToolRegistry::class);
        $waitlist = self::getContainer()->get(WaitlistEntryRepositoryInterface::class);
        $bus = self::getContainer()->get(MessageBusInterface::class);
        \assert($em instanceof EntityManagerInterface);
        \assert($registry instanceof ChatToolRegistry);
        \assert($waitlist instanceof WaitlistEntryRepositoryInterface);
        \assert($bus instanceof MessageBusInterface);
        $this->em = $em;
        $this->registry = $registry;
        $this->waitlist = $waitlist;
        $this->bus = $bus;
    }

    private function reload(User $user): User
    {
        $fresh = $this->em->find(User::class, $user->getId());
        \assert($fresh instanceof User);

        return $fresh;
    }

    public function testJoinIsRejectedWhenSeatsAreStillAvailable(): void
    {
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(5)->assemble())
            ->withSchedule(new \DateTimeImmutable('+10 days'))
            ->assemble();
        $user = UserAssembler::new()->assemble();
        $this->em->persist($lesson);
        $this->em->persist($user);
        $this->em->flush();

        $result = $this->registry->call('user.join_waitlist', new ChatActor($user, ['ROLE_USER']), [
            'lesson_id' => (string) $lesson->getId(),
        ]);

        static::assertFalse($result->ok);
        static::assertCount(0, $this->waitlist->findActiveForUser($user));
    }

    public function testJoinThenOfferOnFreedSeatThenExpire(): void
    {
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(1)->assemble())
            ->withSchedule(new \DateTimeImmutable('+10 days'))
            ->assemble();
        $holder = UserAssembler::new()->withEmail('holder@example.com')->assemble();
        $waiter = UserAssembler::new()->withEmail('waiter@example.com')->assemble();
        $booking = BookingAssembler::new()
            ->withUser($holder)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->withLessons($lesson)
            ->assemble();
        $lesson->addBooking($booking);

        foreach ([$lesson, $holder, $waiter, $booking] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        static::assertSame(0, $lesson->getAvailableSpots(), 'lesson should be full');

        // 1. Waiter joins the (full) waitlist.
        $join = $this->registry->call('user.join_waitlist', new ChatActor($waiter, ['ROLE_USER']), [
            'lesson_id' => (string) $lesson->getId(),
        ]);
        static::assertTrue($join->ok, $join->error ?? $join->summary);
        $entries = $this->waitlist->findActiveForUser($waiter);
        static::assertCount(1, $entries);
        static::assertSame(WaitlistEntry::STATUS_WAITING, $entries[0]->getStatus());

        // Joining again is a no-op, not a duplicate.
        $this->registry->call('user.join_waitlist', new ChatActor($waiter, ['ROLE_USER']), [
            'lesson_id' => (string) $lesson->getId(),
        ]);
        static::assertCount(1, $this->waitlist->findActiveForUser($waiter));

        // 2. Seat frees up; the offer handler promotes + notifies the waiter.
        $this->em->remove($booking);
        $this->em->flush();
        $this->bus->dispatch(new OfferWaitlistSeats($lesson->getId()));

        $this->em->clear();
        $offered = $this->waitlist->findActiveForUser($this->reload($waiter));
        static::assertCount(1, $offered);
        static::assertSame(WaitlistEntry::STATUS_OFFERED, $offered[0]->getStatus());
        static::assertNotNull($offered[0]->getOfferExpiresAt());
        $this->mailer()->assertSentEmailCount(1);
        static::assertNotCount(0, $this->em
            ->getRepository(Notification::class)
            ->findBy([
                'user' => $waiter->getId(),
            ]));

        // 3. The hold lapses; the expiry sweep releases it.
        $this->bus->dispatch(new ExpireWaitlistOffers(new \DateTimeImmutable('+2 hours')));
        $this->em->clear();
        static::assertCount(0, $this->waitlist->findActiveForUser($this->reload($waiter)));
    }

    public function testLeaveWaitlist(): void
    {
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(1)->assemble())
            ->withSchedule(new \DateTimeImmutable('+10 days'))
            ->assemble();
        $holder = UserAssembler::new()->assemble();
        $waiter = UserAssembler::new()->assemble();
        $booking = BookingAssembler::new()
            ->withUser($holder)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->withLessons($lesson)
            ->assemble();
        $lesson->addBooking($booking);
        foreach ([$lesson, $holder, $waiter, $booking] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $actor = new ChatActor($waiter, ['ROLE_USER']);
        $this->registry->call('user.join_waitlist', $actor, ['lesson_id' => (string) $lesson->getId()]);
        static::assertCount(1, $this->waitlist->findActiveForUser($waiter));

        $left = $this->registry->call('user.leave_waitlist', $actor, ['lesson_id' => (string) $lesson->getId()]);
        static::assertTrue($left->ok, $left->error ?? $left->summary);
        static::assertCount(0, $this->waitlist->findActiveForUser($waiter));
    }
}
