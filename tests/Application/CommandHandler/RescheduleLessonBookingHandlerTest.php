<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Entity\Booking;
use App\Entity\MessageType;
use App\Entity\Notification;
use App\Entity\UserMessage;
use App\Message\RescheduleLessonBooking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
class RescheduleLessonBookingHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    public function testRescheduleNotifiesTheCustomerAndRecordsAMessage(): void
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

        $this->bus()
            ->dispatch(
                new RescheduleLessonBooking($booking->getId(), $oldLesson->getId(), $newLesson->getId(), $admin)
            );
        // Drain the queue: the handler itself dispatches a further admin
        // notification command, also routed to the async transport.
        $this->transport('async')
            ->process();
        $this->transport('async')
            ->process();

        $em->clear();

        /** @var Booking $reloaded */
        $reloaded = $em->getRepository(Booking::class)->find($booking->getId());
        self::assertSame(Booking::STATUS_ACTIVE, $reloaded->getStatus());

        $notifications = $em->getRepository(Notification::class)->findBy([
            'user' => $customer,
        ]);
        self::assertCount(1, $notifications);
        self::assertStringContainsString('termin', mb_strtolower((string) $notifications[0]->getTitle()));

        $messages = $em->getRepository(UserMessage::class)->findBy([
            'user' => $customer,
        ]);
        self::assertCount(1, $messages);
        self::assertSame(MessageType::RESCHEDULE_REQUEST, $messages[0]->getType());
        self::assertStringContainsString('Zajęcia poniedziałkowe', $messages[0]->getSubject());
    }
}
