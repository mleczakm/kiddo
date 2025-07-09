<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\CommandHandler\Notification;

use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\CommandHandler\Notification\DailyLessonsReminderHandler;
use App\Tests\Assembler\UserAssembler;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Mailer\Test\TestEmail;

class DailyLessonsReminderHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsReportToAdminsWithLessonsAndAttendants(): void
    {
        $date = new DateTimeImmutable('2025-07-09');
        $admin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $user1 = UserAssembler::new()->withEmail('user1@example.com')->withName('User One')->assemble();
        $user2 = UserAssembler::new()->withEmail('user2@example.com')->withName('User Two')->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($admin);
        $em->persist($user1);
        $em->persist($user2);

        $lesson = \App\Tests\Assembler\LessonAssembler::new()
            ->withMetadata(
                \App\Tests\Assembler\LessonMetadataAssembler::new()
                    ->withTitle('Test Lesson')
                    ->withSchedule($date)
                    ->assemble()
            )
            ->assemble();
        $em->persist($lesson);

        $booking = \App\Tests\Assembler\BookingAssembler::new()
            ->withUser($user1)
            ->withLessons([$lesson])
            ->withStatus('confirmed')
            ->assemble();
        $em->persist($booking);
        $lesson->addBooking($booking);
        $booking2 = \App\Tests\Assembler\BookingAssembler::new()
            ->withUser($user2)
            ->withLessons([$lesson])
            ->withStatus('confirmed')
            ->assemble();
        $em->persist($booking2);
        $lesson->addBooking($booking2);
        $em->flush();

        $handler = self::getContainer()->get(DailyLessonsReminderHandler::class);
        $handler(new DailyLessonsReminder($date));

        $this->mailer()
            ->assertSentEmailCount(1);
        $emails = $this->mailer()
            ->sentEmails();
        /** @var TestEmail $email */
        $email = $emails->first();
        $this->assertSame('admin@example.com', $email->getTo()[0]->getAddress());
        $body = (string) ($email->getHtmlBody() ?? $email->getTextBody());
        $this->assertStringContainsString('User One', $body);
        $this->assertStringContainsString('User Two', $body);
    }
}
