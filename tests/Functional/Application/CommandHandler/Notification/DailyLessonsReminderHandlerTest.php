<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\CommandHandler\Notification;

use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\CommandHandler\Notification\DailyLessonsReminderHandler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use App\Tests\Assembler\BookingAssembler;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

class DailyLessonsReminderHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsUserRemindersWithCorrectContent(): void
    {
        $date = new DateTimeImmutable('2025-07-09 10:00:00', new \DateTimeZone('UTC'));
        $user = UserAssembler::new()->withEmail('user@example.com')->withName('Jan Kowalski')->assemble();
        $admin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($admin);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->withSchedule($date)->assemble())
            ->assemble();
        $em->persist($lesson);
        $booking = BookingAssembler::new()->withUser($user)->withLessons([$lesson])->withStatus(
            'confirmed'
        )->assemble();
        $lesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        $handler = self::getContainer()->get(DailyLessonsReminderHandler::class);
        $handler(new DailyLessonsReminder($date));

        $emails = $this->mailer()
            ->sentEmails();
        $userEmail = $emails->whereTo('user@example.com');
        $userEmail = $userEmail->first();
        $body = (string) ($userEmail->getHtmlBody() ?? $userEmail->getTextBody());
        $this->assertStringContainsString('Cześć Jan Kowalski', $body);
        $this->assertStringContainsString('Joga', $body);
        $this->assertStringContainsString('09.07', $body);
        $this->assertStringContainsString('12:00', $body);
    }
}
