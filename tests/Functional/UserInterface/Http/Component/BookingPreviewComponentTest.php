<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Component;

use App\Repository\BookingRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class BookingPreviewComponentTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private BookingRepository $bookingRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->bookingRepository = self::getContainer()->get(BookingRepository::class);
    }

    public function testFindForUserAndLessonReturnsBookingsForUserAndLesson(): void
    {
        $now = Clock::get()->now();

        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())->withSchedule($now->modify('+2 days'))
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withStatus('paid')
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->assemble();

        $this->em->persist($user);
        $this->em->persist($lesson);
        $this->em->persist($payment);
        $this->em->persist($booking);
        $this->em->flush();

        $bookings = $this->bookingRepository->findForUserAndLesson($user, $lesson);

        $this->assertCount(1, $bookings);
        $this->assertSame($booking->getId(), $bookings[0]->getId());
    }

    public function testFindForUserAndLessonReturnsOnlyBookingsForSpecificLesson(): void
    {
        $now = Clock::get()->now();

        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

        $lesson1 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())->withSchedule($now->modify('+2 days'))
            ->assemble();

        $lesson2 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())->withSchedule($now->modify('+3 days'))
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withStatus('paid')
            ->assemble();

        $booking1 = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson1)
            ->assemble();

        $booking2 = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson2)
            ->assemble();

        $this->em->persist($user);
        $this->em->persist($lesson1);
        $this->em->persist($lesson2);
        $this->em->persist($payment);
        $this->em->persist($booking1);
        $this->em->persist($booking2);
        $this->em->flush();

        $bookings = $this->bookingRepository->findForUserAndLesson($user, $lesson1);

        $this->assertCount(1, $bookings);
        $this->assertSame($booking1->getId(), $bookings[0]->getId());
    }

    public function testFindForUserAndLessonReturnsEmptyArrayForDifferentUser(): void
    {
        $now = Clock::get()->now();

        $user1 = UserAssembler::new()
            ->withEmail('user1@example.com')
            ->withName('User One')
            ->assemble();

        $user2 = UserAssembler::new()
            ->withEmail('user2@example.com')
            ->withName('User Two')
            ->assemble();

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())->withSchedule($now->modify('+2 days'))
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user1)
            ->withStatus('paid')
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user1)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->assemble();

        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->persist($lesson);
        $this->em->persist($payment);
        $this->em->persist($booking);
        $this->em->flush();

        $bookings = $this->bookingRepository->findForUserAndLesson($user2, $lesson);

        $this->assertEmpty($bookings);
    }

    public function testFindForUserAndLessonReturnsMultipleBookingsForSameLesson(): void
    {
        $now = Clock::get()->now();

        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())->withSchedule($now->modify('+2 days'))
            ->assemble();

        $payment1 = PaymentAssembler::new()
            ->withUser($user)
            ->withStatus('paid')
            ->assemble();

        $payment2 = PaymentAssembler::new()
            ->withUser($user)
            ->withStatus('pending')
            ->assemble();

        $booking1 = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment1)
            ->withLessons($lesson)
            ->assemble();

        $booking2 = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment2)
            ->withLessons($lesson)
            ->assemble();

        $this->em->persist($user);
        $this->em->persist($lesson);
        $this->em->persist($payment1);
        $this->em->persist($payment2);
        $this->em->persist($booking1);
        $this->em->persist($booking2);
        $this->em->flush();

        $bookings = $this->bookingRepository->findForUserAndLesson($user, $lesson);

        $this->assertCount(2, $bookings);

        $bookingIds = array_map(fn($b) => (string) $b->getId(), $bookings);
        $this->assertContains((string) $booking1->getId(), $bookingIds);
        $this->assertContains((string) $booking2->getId(), $bookingIds);
    }
}
