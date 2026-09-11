<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendRescheduleAdminNotificationCommand;
use App\Application\CommandHandler\Notification\SendRescheduleAdminNotificationHandler;
use App\Entity\FinanceContact;
use App\Entity\Notification;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class SendRescheduleAdminNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsRescheduleNotificationToFinanceContactAndConnectedHostsOnly(): void
    {
        $fromDate = new DateTimeImmutable('2025-08-24 10:00:00');
        $toDate = new DateTimeImmutable('2025-08-26 12:30:00');

        $user = UserAssembler::new()->withEmail('user@example.com')->withName('Jan Kowalski')->assemble();

        $finance = UserAssembler::new()->withEmail('finance@example.com')->assemble();
        $oldHost = UserAssembler::new()->withEmail('old-host@example.com')->withRoles('ROLE_HOST')->assemble();
        $newHost = UserAssembler::new()->withEmail('new-host@example.com')->withRoles('ROLE_HOST')->assemble();
        $unrelatedAdmin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($finance);
        $em->persist($oldHost);
        $em->persist($newHost);
        $em->persist($unrelatedAdmin);
        $em->persist(new FinanceContact($finance));

        $oldLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->assemble())
            ->withSchedule($fromDate)
            ->assemble();
        $newLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->assemble())
            ->withSchedule($toDate)
            ->assemble();
        $oldLesson->addInstructor($oldHost);
        $newLesson->addInstructor($newHost);
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

        $this->mailer()->assertSentEmailCount(3);

        $emails = $this->mailer()->sentEmails();
        $financeEmail = $emails->whereTo($finance->getEmail())->first();
        $oldHostEmail = $emails->whereTo($oldHost->getEmail())->first();
        $newHostEmail = $emails->whereTo($newHost->getEmail())->first();

        // Subject contains user email and lesson title
        static::assertStringContainsString('user@example.com', (string) $financeEmail->getSubject());
        static::assertStringContainsString('Joga', (string) $oldHostEmail->getSubject());
        static::assertStringContainsString('Joga', (string) $newHostEmail->getSubject());
        static::assertCount(0, $emails->whereTo($unrelatedAdmin->getEmail()));

        // Body contains key information
        $body1 = (string) ($financeEmail->getHtmlBody() ?? $financeEmail->getTextBody());
        static::assertStringContainsString('user@example.com', $body1);
        static::assertStringContainsString('Joga', $body1);
        static::assertStringContainsString('Powód', $body1);
        static::assertStringContainsString('Urlop', $body1);

        static::assertCount(1, $em->getRepository(Notification::class)->findBy(['user' => $finance]));
        static::assertCount(1, $em->getRepository(Notification::class)->findBy(['user' => $oldHost]));
        static::assertCount(1, $em->getRepository(Notification::class)->findBy(['user' => $newHost]));
        static::assertCount(0, $em->getRepository(Notification::class)->findBy(['user' => $unrelatedAdmin]));
    }

    public function testDoesNotSendWhenNoAdmins(): void
    {
        $date = new DateTimeImmutable('2025-08-24 10:00:00');

        $user = UserAssembler::new()->withEmail('user@example.com')->withName('Jan Kowalski')->assemble();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);

        $oldLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Pilates')->assemble())
            ->withSchedule($date)
            ->assemble();
        $newLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Pilates')->assemble())
            ->withSchedule($date->modify('+2 days'))
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

        $this->mailer()->assertSentEmailCount(0);
    }
}
