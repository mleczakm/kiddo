<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Entity\Booking;
use App\Message\CancelLessonBooking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * End-to-end proof that command-bus-routed actions are picked up generically
 * by ActivityLogMiddleware, with no changes needed in the handler itself.
 */
#[Group('functional')]
final class ActivityLogMiddlewareTest extends KernelTestCase
{
    use InteractsWithMailer;
    use InteractsWithMessenger;

    public function testCancellingABookingWritesAnActivityLogEntry(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = UserAssembler::new()->withName('Ewa Zielińska')->assemble();
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
        $this->transport('async')->process();
        $this->transport('async')->process();

        $activityLogs = $em->getRepository(ActivityLog::class)->findBy(['type' => ActivityType::BOOKING_CANCELLED]);

        self::assertCount(1, $activityLogs);
        self::assertSame($user->getId(), $activityLogs[0]->getSubject()?->getId());
        self::assertStringContainsString('Ewa Zielińska', $activityLogs[0]->getTitle());
        self::assertSame('Sensoplastyka', $activityLogs[0]->getSummary());
        self::assertNotNull($activityLogs[0]->getUrl());
    }
}
