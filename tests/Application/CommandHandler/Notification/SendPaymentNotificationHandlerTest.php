<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendPaymentNotificationCommand;
use App\Application\CommandHandler\Notification\SendPaymentNotificationHandler;
use App\Entity\Notification;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
class SendPaymentNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsPaymentNotificationToUserAndAdmins(): void
    {
        $date = new DateTimeImmutable('2025-08-24 10:00:00');
        $user = UserAssembler::new()->withEmail('user@example.com')->withName('Jan Kowalski')->assemble();
        $admin1 = UserAssembler::new()->withEmail('admin1@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $admin2 = UserAssembler::new()->withEmail('admin2@example.com')->withRoles('ROLE_ADMIN')->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($admin1);
        $em->persist($admin2);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->assemble())
            ->withSchedule($date)
            ->assemble();
        $em->persist($lesson);

        $booking = BookingAssembler::new()->withUser($user)->withLessons($lesson)->assemble();
        $lesson->addBooking($booking);
        $em->persist($booking);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('123.45', 'PLN'))
            ->withCreatedAt(new DateTimeImmutable('2025-08-24 12:00:00'))
            ->assemble();
        $payment->addBooking($booking);
        $em->persist($payment);
        $em->flush();

        $handler = self::getContainer()->get(SendPaymentNotificationHandler::class);
        $handler(new SendPaymentNotificationCommand($payment));

        $this->mailer()->assertSentEmailCount(3);
        $emails = $this->mailer()->sentEmails();
        $userEmail = $emails->whereTo($user->getEmail())->first();
        $adminEmail1 = $emails->whereTo($admin1->getEmail())->first();
        $adminEmail2 = $emails->whereTo($admin2->getEmail())->first();

        self::assertStringContainsString('user@example.com', $userEmail->getTo()[0]->getAddress());
        self::assertStringContainsString('admin1@example.com', $adminEmail1->getTo()[0]->getAddress());
        self::assertStringContainsString('admin2@example.com', $adminEmail2->getTo()[0]->getAddress());
        self::assertStringContainsString('user@example.com', (string) $adminEmail2->getHtmlBody());
        self::assertStringContainsString('user@example.com', (string) $adminEmail1->getHtmlBody());
        self::assertStringContainsString('<user@example.com>', (string) $adminEmail2->getSubject());
        self::assertStringContainsString('<user@example.com>', (string) $adminEmail1->getSubject());

        self::assertStringContainsString('123,45', (string) $userEmail->getHtmlBody());
        self::assertStringContainsString('Joga', (string) $userEmail->getHtmlBody());
        self::assertStringContainsString('Joga', (string) $userEmail->getSubject());
        self::assertStringContainsString('Joga', (string) $adminEmail1->getSubject());
        self::assertStringContainsString('Joga', (string) $adminEmail1->getSubject());
        self::assertStringContainsString('niedziela 24 sie', (string) $userEmail->getHtmlBody());

        $notifications = $em->getRepository(Notification::class)->findAll();
        self::assertCount(3, $notifications); // user + 2 admins
        $titles = array_map(static fn(Notification $n) => $n->getTitle(), $notifications);
        self::assertContains('Płatność potwierdzona', $titles);
        self::assertContains('Nowa płatność', $titles);
    }
}
