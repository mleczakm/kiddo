<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\AddBooking;
use App\Application\CommandHandler\AddBookingHandler;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\TicketType;
use App\Repository\BookingRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class AddBookingHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testCreatesBookingFromPrimitives(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();

        $em->persist($user);
        $em->persist($lesson);
        $em->flush();

        $userId = $user->getId();
        self::assertNotNull($userId);
        $lessonId = (string) $lesson->getId();
        $paymentCode = 'AB12';

        /** @var AddBookingHandler $handler */
        $handler = self::getContainer()->get(AddBookingHandler::class);
        $handler(new AddBooking(
            userId: $userId,
            lessonId: $lessonId,
            ticketType: TicketType::ONE_TIME->value,
            childId: null,
            paymentCode: $paymentCode,
        ));

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        $bookings = $bookingRepository->findBy([
            'user' => $userId,
        ]);

        self::assertCount(1, $bookings);

        $booking = $bookings[0];
        self::assertInstanceOf(Booking::class, $booking);
        self::assertSame(Booking::STATUS_PENDING, $booking->getStatus());
        self::assertCount(1, $booking->getLessons());
        $bookedLesson = $booking->getLessons()
            ->first();
        self::assertInstanceOf(Lesson::class, $bookedLesson);
        self::assertSame($lessonId, (string) $bookedLesson->getId());
        self::assertSame($paymentCode, $booking->getPayment()?->getPaymentCode()?->getCode());

        $this->mailer()
            ->assertSentEmailCount(1);
        $email = $this->mailer()
            ->sentEmails()
            ->first();
        self::assertStringContainsString($paymentCode, (string) ($email->getHtmlBody() ?? $email->getTextBody()));
    }
}
