<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendRescheduleAdminNotificationCommand;
use App\Application\CommandHandler\Notification\SendRescheduleAdminNotificationHandler;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class SendRescheduleAdminNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsRescheduleNotificationToAdmins(): void
    {
        $fromDate = new DateTimeImmutable('2025-08-24 10:00:00');
        $toDate = new DateTimeImmutable('2025-08-26 12:30:00');

        $user = UserAssembler::new()
            ->withEmail('user@example.com')
            ->withName('Jan Kowalski')
            ->assemble();

        $admin1 = UserAssembler::new()->withEmail('admin1@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $admin2 = UserAssembler::new()->withEmail('admin2@example.com')->withRoles('ROLE_ADMIN')->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($admin1);
        $em->persist($admin2);

        $oldLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->withSchedule($fromDate)->assemble())
            ->assemble();
        $newLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->withSchedule($toDate)->assemble())
            ->assemble();
        $em->persist($oldLesson);
        $em->persist($newLesson);

        $booking = BookingAssembler::new()->withUser($user)->withLessons($oldLesson)->assemble();
        $oldLesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        $handler = self::getContainer()->get(SendRescheduleAdminNotificationHandler::class);
        $handler(new SendRescheduleAdminNotificationCommand(
            booking: $booking,
            oldLesson: $oldLesson,
            newLesson: $newLesson,
            rescheduledBy: $user,
            reason: 'Urlop',
        ));

        $this->mailer()
            ->assertSentEmailCount(2);

        $emails = $this->mailer()
            ->sentEmails();
        $adminEmail1 = $emails->whereTo($admin1->getEmail())
            ->first();
        $adminEmail2 = $emails->whereTo($admin2->getEmail())
            ->first();

        // Subject contains user email and lesson title
        self::assertStringContainsString('user@example.com', (string) $adminEmail1->getSubject());
        self::assertStringContainsString('Joga', (string) $adminEmail1->getSubject());
        self::assertStringContainsString('Joga', (string) $adminEmail2->getSubject());

        // Body contains key information
        $body1 = (string) ($adminEmail1->getHtmlBody() ?? $adminEmail1->getTextBody());
        self::assertStringContainsString('user@example.com', $body1);
        self::assertStringContainsString('Joga', $body1);
        self::assertStringContainsString('Powód', $body1);
        self::assertStringContainsString('Urlop', $body1);
    }

    public function testDoesNotSendWhenNoAdmins(): void
    {
        $date = new DateTimeImmutable('2025-08-24 10:00:00');

        $user = UserAssembler::new()->withEmail('user@example.com')->withName('Jan Kowalski')->assemble();
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);

        $oldLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Pilates')->withSchedule($date)->assemble())
            ->assemble();
        $newLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Pilates')->withSchedule($date->modify('+2 days'))
                ->assemble())
            ->assemble();
        $em->persist($oldLesson);
        $em->persist($newLesson);

        $booking = BookingAssembler::new()->withUser($user)->withLessons($oldLesson)->assemble();
        $oldLesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        $handler = self::getContainer()->get(SendRescheduleAdminNotificationHandler::class);
        $handler(new SendRescheduleAdminNotificationCommand(
            booking: $booking,
            oldLesson: $oldLesson,
            newLesson: $newLesson,
            rescheduledBy: $user,
            reason: null,
        ));

        $this->mailer()
            ->assertSentEmailCount(0);
    }
}
