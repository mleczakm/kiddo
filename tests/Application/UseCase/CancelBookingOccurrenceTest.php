<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\CancelBookingOccurrence;
use App\Entity\Booking;
use App\Entity\BookingOccurrence;
use App\Entity\Lesson;
use App\Entity\Notification;
use App\Entity\User;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class CancelBookingOccurrenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private CancelBookingOccurrence $cancelBookingOccurrence;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var CancelBookingOccurrence $cancelBookingOccurrence */
        $cancelBookingOccurrence = $container->get(CancelBookingOccurrence::class);
        $this->cancelBookingOccurrence = $cancelBookingOccurrence;
    }

    public function testCancellingTheOnlyLessonCancelsTheWholeBooking(): void
    {
        $customer = UserAssembler::new()->assemble();
        $lesson = $this->lessonIn('+2 days');
        $booking = $this->persistBooking($customer, $lesson);
        $customerId = (int) $customer->getId();

        ($this->cancelBookingOccurrence)($booking->getId(), $lesson->getId(), $customerId, 'Choroba');
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Booking::class, $reloaded);
        static::assertSame(Booking::STATUS_CANCELLED, $reloaded->getStatus());
        static::assertSame(
            BookingOccurrence::STATUS_CANCELLED,
            $reloaded->findOccurrence($lesson->getId())?->getStatus(),
        );
    }

    public function testCancellingOneOfManyLessonsKeepsTheBookingActiveAndNotifiesStaff(): void
    {
        $customer = UserAssembler::new()->withName('Anna Kowalska')->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $first = $this->lessonIn('+2 days');
        $second = $this->lessonIn('+9 days');
        $this->em->persist($admin);
        $booking = $this->persistBooking($customer, $first, $second);
        $customerId = (int) $customer->getId();

        ($this->cancelBookingOccurrence)($booking->getId(), $first->getId(), $customerId, 'Urlop');
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->em->find(Booking::class, $booking->getId());
        static::assertInstanceOf(Booking::class, $reloaded);
        static::assertSame(Booking::STATUS_ACTIVE, $reloaded->getStatus());
        static::assertTrue($reloaded->isLessonCancelled($first));
        static::assertFalse($reloaded->isLessonCancelled($second));
        static::assertSame($customerId, $reloaded->getLessonsMap()->getCancelledByUserId($first->getId()));

        $adminNotifications = $this->em
            ->getRepository(Notification::class)
            ->findBy([
                'user' => $admin->getId(),
            ]);
        static::assertCount(1, $adminNotifications, 'Staff must be told about a partial cancellation');
        static::assertCount(
            0,
            $this->em
                ->getRepository(Notification::class)
                ->findBy([
                    'user' => $customerId,
                ]),
            'The cancelling customer is excluded from the staff notification',
        );
    }

    public function testCancellingAnAlreadyCancelledLessonOfAnActiveBookingIsANoOp(): void
    {
        $customer = UserAssembler::new()->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $first = $this->lessonIn('+2 days');
        $second = $this->lessonIn('+9 days');
        $this->em->persist($admin);
        $booking = $this->persistBooking($customer, $first, $second);
        $customerId = (int) $customer->getId();

        ($this->cancelBookingOccurrence)($booking->getId(), $first->getId(), $customerId, 'Raz');
        $this->em->flush();
        $notificationsAfterFirst = count($this->em->getRepository(Notification::class)->findAll());

        ($this->cancelBookingOccurrence)($booking->getId(), $first->getId(), $customerId, 'Dwa');
        $this->em->flush();

        static::assertSame(Booking::STATUS_ACTIVE, $booking->getStatus());
        static::assertCount(
            $notificationsAfterFirst,
            $this->em->getRepository(Notification::class)->findAll(),
            'A repeated cancel must not notify staff again',
        );
    }

    public function testCancellingAnAlreadyCancelledBookingIsANoOp(): void
    {
        $customer = UserAssembler::new()->assemble();
        $lesson = $this->lessonIn('+2 days');
        $booking = $this->persistBooking($customer, $lesson, status: Booking::STATUS_CANCELLED);

        ($this->cancelBookingOccurrence)($booking->getId(), $lesson->getId(), (int) $customer->getId(), null);
        $this->em->flush();

        static::assertSame(Booking::STATUS_CANCELLED, $booking->getStatus());
        static::assertCount(0, $this->em->getRepository(Notification::class)->findAll());
    }

    public function testAnUnknownUserIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User 999999999 not found');

        ($this->cancelBookingOccurrence)(new Ulid(), new Ulid(), 999_999_999, null);
    }

    public function testAnUnknownBookingIsALoggedNoOp(): void
    {
        $customer = UserAssembler::new()->assemble();
        $this->em->persist($customer);
        $this->em->flush();

        ($this->cancelBookingOccurrence)(new Ulid(), new Ulid(), (int) $customer->getId(), null);
        $this->em->flush();

        static::assertCount(0, $this->em->getRepository(Notification::class)->findAll());
    }

    public function testALessonThatIsNotPartOfTheBookingLeavesItUntouched(): void
    {
        $customer = UserAssembler::new()->assemble();
        $lesson = $this->lessonIn('+2 days');
        $booking = $this->persistBooking($customer, $lesson);

        ($this->cancelBookingOccurrence)($booking->getId(), new Ulid(), (int) $customer->getId(), null);
        $this->em->flush();

        static::assertSame(Booking::STATUS_ACTIVE, $booking->getStatus());
        static::assertFalse($booking->isLessonCancelled($lesson));
    }

    private function lessonIn(string $offset): Lesson
    {
        return LessonAssembler::new()->withSchedule(Clock::get()->now()->modify($offset))->assemble();
    }

    private function persistBooking(
        User $customer,
        Lesson $lesson,
        ?Lesson $second = null,
        string $status = Booking::STATUS_ACTIVE,
    ): Booking {
        $lessons = $second instanceof Lesson ? [$lesson, $second] : [$lesson];
        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons(...$lessons)
            ->withStatus($status)
            ->assemble();

        foreach ([$customer, ...$lessons, $booking] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return $booking;
    }
}
