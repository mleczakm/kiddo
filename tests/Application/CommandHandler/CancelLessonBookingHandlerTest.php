<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Entity\Booking;
use App\Entity\MessageType;
use App\Entity\Notification;
use App\Entity\UserMessage;
use App\Message\CancelLessonBooking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
class CancelLessonBookingHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;
    use InteractsWithMessenger;

    public function testCancellingTheOnlyLessonCancelsTheBookingAndNotifies(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = UserAssembler::new()->assemble();
        $em->persist($user);

        $lesson = LessonAssembler::new()
            ->withTitle('Sensoplastyka')
            ->withSchedule(Clock::get()->now()->modify('+1 day'))
            ->assemble();
        $em->persist($lesson);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $em->persist($booking);
        $em->flush();

        $this->bus()->dispatch(new CancelLessonBooking(
            $booking->getId(),
            $lesson->getId(),
            $user,
            'Nie damy rady przyjść',
        ));
        // The cancel handler dispatches a further notification command, also
        // routed to the async transport, so drain the queue until it settles.
        $this->transport('async')->process();
        $this->transport('async')->process();

        $em->clear();
        /** @var Booking $reloaded */
        $reloaded = $em->getRepository(Booking::class)->find($booking->getId());
        self::assertSame(Booking::STATUS_CANCELLED, $reloaded->getStatus());

        // Cancellation email sent to the customer (workflow.booking.transition.cancel fired)
        $this->mailer()->assertSentEmailCount(1);
        $sentEmail = $this->mailer()->sentEmails()->first();
        self::assertSame($user->getEmail(), $sentEmail->getTo()[0]->getAddress());

        // In-app notification created for the customer
        $notifications = $em->getRepository(Notification::class)->findBy(['user' => $user]);
        self::assertCount(1, $notifications);

        // Admin-visible message recorded about the cancellation
        $messages = $em->getRepository(UserMessage::class)->findBy(['user' => $user]);
        self::assertCount(1, $messages);
        self::assertSame(MessageType::CANCELLATION_REQUEST, $messages[0]->getType());
        self::assertStringContainsString('Sensoplastyka', $messages[0]->getSubject());
    }

    public function testCancellingOneLessonOfAMultiLessonBookingKeepsBookingActive(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = UserAssembler::new()->assemble();
        $em->persist($user);

        $lesson1 = LessonAssembler::new()
            ->withTitle('Zajęcia 1')
            ->withSchedule(Clock::get()->now()->modify('+1 day'))
            ->assemble();
        $lesson2 = LessonAssembler::new()
            ->withTitle('Zajęcia 2')
            ->withSchedule(Clock::get()->now()->modify('+8 days'))
            ->assemble();
        $em->persist($lesson1);
        $em->persist($lesson2);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson1, $lesson2)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $em->persist($booking);
        $em->flush();

        $this->bus()->dispatch(new CancelLessonBooking(
            $booking->getId(),
            $lesson1->getId(),
            $user,
        ));
        $this->transport('async')->process();

        $em->clear();
        /** @var Booking $reloaded */
        $reloaded = $em->getRepository(Booking::class)->find($booking->getId());
        // One active lesson remains, so the booking as a whole is still active
        self::assertSame(Booking::STATUS_ACTIVE, $reloaded->getStatus());

        // No workflow transition fired at the booking level, so no cancellation email
        $this->mailer()->assertSentEmailCount(0);

        // The cancellation is still recorded for admin visibility
        $messages = $em->getRepository(UserMessage::class)->findBy(['user' => $user]);
        self::assertCount(1, $messages);
    }
}
