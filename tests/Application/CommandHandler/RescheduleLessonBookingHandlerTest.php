<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Entity\Booking;
use App\Entity\BookingOccurrence;
use App\Entity\Lesson;
use App\Entity\Notification;
use App\Entity\User;
use App\Message\RescheduleLessonBooking;
use App\MessageHandler\RescheduleLessonBookingHandler;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
class RescheduleLessonBookingHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    public function testRescheduleNotifiesTheCustomer(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        $customer = UserAssembler::new()->assemble();
        $em->persist($customer);

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);

        $oldLesson = LessonAssembler::new()
            ->withTitle('Zajęcia poniedziałkowe')
            ->withSchedule(Clock::get()->now()->modify('+1 day'))
            ->assemble();
        $newLesson = LessonAssembler::new()
            ->withTitle('Zajęcia czwartkowe')
            ->withSchedule(Clock::get()->now()->modify('+4 days'))
            ->assemble();
        $em->persist($oldLesson);
        $em->persist($newLesson);

        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons($oldLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $em->persist($booking);
        $em->flush();

        $this->bus()->dispatch(
            new RescheduleLessonBooking($booking->getId(), $oldLesson->getId(), $newLesson->getId(), $admin),
        );
        // Drain the queue: the handler itself dispatches a further admin
        // notification command, also routed to the async transport.
        $this->transport('async')->process();
        $this->transport('async')->process();

        $em->clear();

        /** @var Booking $reloaded */
        $reloaded = $em->getRepository(Booking::class)->find($booking->getId());
        static::assertSame(Booking::STATUS_ACTIVE, $reloaded->getStatus());

        $notifications = $em->getRepository(Notification::class)->findBy([
            'user' => $customer,
        ]);
        static::assertCount(1, $notifications);
        static::assertStringContainsString('termin', mb_strtolower((string) $notifications[0]->getTitle()));

        // The reschedule must survive a fresh DB round-trip (regression
        // guard: LessonMap is a custom-typed field — see
        // CancelLessonBookingHandlerTest for the same concern on cancel).
        static::assertTrue(
            $reloaded->isLessonRescheduled($oldLesson),
            'old lesson should be marked rescheduled after reload',
        );
        static::assertSame(
            BookingOccurrence::STATUS_RESCHEDULED,
            $reloaded->findOccurrence($oldLesson->getId())?->getStatus(),
        );
        static::assertTrue($reloaded->findOccurrence($newLesson->getId())?->isActive() ?? false);
        // $newLesson is a detached instance from before em->clear(), so compare
        // by id rather than Collection::contains() (which is identity-based).
        $reloadedLessonIds = array_map(static fn($l) => $l->getId()->toRfc4122(), $reloaded->getLessons()->toArray());
        static::assertContains(
            $newLesson->getId()->toRfc4122(),
            $reloadedLessonIds,
            'new lesson should be attached to the booking after reload',
        );
        static::assertSame(
            $admin->getId(),
            $reloaded->getLessonsMap()->getRescheduledByUserId($oldLesson->getId()),
            'reschedule should record who rescheduled it',
        );
    }

    public function testAnUnknownBookingIsIgnored(): void
    {
        [$em, $customer, $admin, $oldLesson, $newLesson] = $this->arrangeCustomerBooking();
        $this->handle(new RescheduleLessonBooking(new Ulid(), $oldLesson->getId(), $newLesson->getId(), $admin));

        static::assertCount(0, $em->getRepository(Notification::class)->findBy([
            'user' => $customer,
        ]));
    }

    public function testALessonThatIsNotInTheBookingIsIgnored(): void
    {
        [$em, $customer, $admin, $oldLesson, $newLesson, $booking] = $this->arrangeCustomerBooking();

        $this->handle(new RescheduleLessonBooking($booking->getId(), $newLesson->getId(), $oldLesson->getId(), $admin));

        static::assertFalse($booking->isLessonRescheduled($newLesson));
        static::assertTrue($booking->findOccurrence($oldLesson->getId())?->isActive() ?? false);
        static::assertCount(0, $em->getRepository(Notification::class)->findBy([
            'user' => $customer,
        ]));
    }

    public function testALessonThatWasAlreadyCancelledCannotBeRescheduled(): void
    {
        [$em, $customer, $admin, $oldLesson, $newLesson, $booking] =
            $this->arrangeCustomerBooking(bookedLessonCount: 2);
        $booking->cancelLesson($oldLesson->getId()->toRfc4122(), null, $customer);
        $em->flush();

        $this->handle(new RescheduleLessonBooking($booking->getId(), $oldLesson->getId(), $newLesson->getId(), $admin));

        static::assertTrue($booking->isLessonCancelled($oldLesson));
        static::assertFalse($booking->isLessonRescheduled($oldLesson));
    }

    public function testAnUnknownTargetLessonIsIgnored(): void
    {
        [$em, $customer, $admin, $oldLesson, , $booking] = $this->arrangeCustomerBooking();

        $this->handle(new RescheduleLessonBooking($booking->getId(), $oldLesson->getId(), new Ulid(), $admin));

        static::assertFalse($booking->isLessonRescheduled($oldLesson));
        static::assertCount(0, $em->getRepository(Notification::class)->findBy([
            'user' => $customer,
        ]));
    }

    public function testACustomerCannotRescheduleWithinTheLast24HoursOfTheLesson(): void
    {
        [, $customer, , $oldLesson, $newLesson, $booking] = $this->arrangeCustomerBooking(oldLessonIn: '+3 hours');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reschedule is not allowed for this booking');
        $this->handle(
            new RescheduleLessonBooking($booking->getId(), $oldLesson->getId(), $newLesson->getId(), $customer),
        );
    }

    public function testAFullTargetLessonIsRejectedEvenForAnAdmin(): void
    {
        [$em, , $admin, $oldLesson, $newLesson, $booking] = $this->arrangeCustomerBooking(newLessonCapacity: 1);
        $otherCustomer = UserAssembler::new()->assemble();
        $competingBooking = BookingAssembler::new()
            ->withUser($otherCustomer)
            ->withLessons($newLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        // The inverse side of Lesson::$bookings is not populated by the assembler.
        $newLesson->addBooking($competingBooking);
        $em->persist($otherCustomer);
        $em->persist($competingBooking);
        $em->flush();

        try {
            $this->handle(
                new RescheduleLessonBooking($booking->getId(), $oldLesson->getId(), $newLesson->getId(), $admin),
            );
            static::fail('Expected a reschedule into a full lesson to be rejected.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Selected lesson has no available spots', $exception->getMessage());
        }

        static::assertFalse($booking->isLessonRescheduled($oldLesson));
    }

    private function handle(RescheduleLessonBooking $command): void
    {
        /** @var RescheduleLessonBookingHandler $handler */
        $handler = self::getContainer()->get(RescheduleLessonBookingHandler::class);
        $handler($command);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
    }

    /**
     * @return array{EntityManagerInterface, User, User, Lesson, Lesson, Booking}
     */
    private function arrangeCustomerBooking(
        string $oldLessonIn = '+3 days',
        int $newLessonCapacity = 10,
        int $bookedLessonCount = 1,
    ): array {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $customer = UserAssembler::new()->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $oldLesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify($oldLessonIn))->assemble();
        $newLesson = LessonAssembler::new()
            ->withSchedule(Clock::get()->now()->modify('+8 days'))
            ->withCapacity($newLessonCapacity)
            ->assemble();
        $bookedLessons = $bookedLessonCount > 1 ? [$oldLesson, $newLesson] : [$oldLesson];
        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons(...$bookedLessons)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        foreach ([$customer, $admin, $oldLesson, $newLesson, $booking] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [$em, $customer, $admin, $oldLesson, $newLesson, $booking];
    }
}
