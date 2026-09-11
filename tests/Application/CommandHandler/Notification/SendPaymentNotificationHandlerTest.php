<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendPaymentNotificationCommand;
use App\Application\CommandHandler\Notification\SendPaymentNotificationHandler;
use App\Entity\FinanceContact;
use App\Entity\Notification;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
class SendPaymentNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsPaymentNotificationToCustomerFinanceContactAndConnectedHostOnly(): void
    {
        $date = new DateTimeImmutable('2025-08-24 10:00:00');
        $user = UserAssembler::new()->withEmail('user@example.com')->withName('Jan Kowalski')->assemble();
        $finance = UserAssembler::new()->withEmail('finance@example.com')->assemble();
        $host = UserAssembler::new()->withEmail('host@example.com')->withRoles('ROLE_HOST')->assemble();
        $unrelatedAdmin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($finance);
        $em->persist($host);
        $em->persist($unrelatedAdmin);
        $em->persist(new FinanceContact($finance));

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->assemble())
            ->withSchedule($date)
            ->assemble();
        $lesson->addInstructor($host);
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
        $financeEmail = $emails->whereTo($finance->getEmail())->first();
        $hostEmail = $emails->whereTo($host->getEmail())->first();

        static::assertStringContainsString('user@example.com', $userEmail->getTo()[0]->getAddress());
        static::assertStringContainsString('finance@example.com', $financeEmail->getTo()[0]->getAddress());
        static::assertStringContainsString('host@example.com', $hostEmail->getTo()[0]->getAddress());
        static::assertStringContainsString('user@example.com', (string) $financeEmail->getHtmlBody());
        static::assertStringContainsString('user@example.com', (string) $hostEmail->getHtmlBody());
        static::assertStringContainsString('<user@example.com>', (string) $financeEmail->getSubject());
        static::assertStringContainsString('<user@example.com>', (string) $hostEmail->getSubject());
        static::assertCount(0, $emails->whereTo($unrelatedAdmin->getEmail()));

        static::assertStringContainsString('123,45', (string) $userEmail->getHtmlBody());
        static::assertStringContainsString('Joga', (string) $userEmail->getHtmlBody());
        static::assertStringContainsString('Joga', (string) $userEmail->getSubject());
        static::assertStringContainsString('Joga', (string) $financeEmail->getSubject());
        static::assertStringContainsString('Joga', (string) $hostEmail->getSubject());
        static::assertStringContainsString('niedziela 24 sie', (string) $userEmail->getHtmlBody());

        // The confirmation email carries an .ics calendar file and an "add to calendar" link.
        $userEmail->assertHasFile('kalendarz.ics', 'text/calendar');
        $icsAttachment = $userEmail->getAttachments()[0];
        static::assertStringContainsString('BEGIN:VEVENT', $icsAttachment->getBody());
        static::assertStringContainsString('SUMMARY:Joga', $icsAttachment->getBody());
        static::assertStringContainsString('calendar.google.com/calendar/render', (string) $userEmail->getHtmlBody());
        static::assertStringContainsString('Dodaj do kalendarza', (string) $userEmail->getHtmlBody());

        static::assertSame([], $financeEmail->getAttachments());

        $notifications = $em->getRepository(Notification::class)->findAll();
        static::assertCount(3, $notifications); // customer + finance contact + connected host
        $titles = array_map(static fn(Notification $n) => $n->getTitle(), $notifications);
        static::assertContains('Płatność potwierdzona', $titles);
        static::assertContains('Nowa płatność', $titles);
        static::assertCount(0, $em->getRepository(Notification::class)->findBy(['user' => $unrelatedAdmin]));
    }
}
