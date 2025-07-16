<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendBookingCancellationNotificationCommand;
use App\Application\CommandHandler\Notification\SendBookingCancellationNotificationHandler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use App\Tests\Assembler\BookingAssembler;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

class SendBookingCancellationNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsCancellationNotificationWithCorrectContent(): void
    {
        $date = new DateTimeImmutable('2025-07-16 10:00:00');
        $user = UserAssembler::new()
            ->withEmail('user@example.com')
            ->withName('Jan Kowalski')
            ->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new() ->withTitle('Joga') ->withSchedule($date) ->assemble())
            ->assemble();

        $em->persist($lesson);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons([$lesson])
            ->withStatus('cancelled')
            ->assemble();

        $lesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        $handler = self::getContainer()->get(SendBookingCancellationNotificationHandler::class);
        $handler(new SendBookingCancellationNotificationCommand($booking));

        $email = $this->mailer()
            ->sentEmails()
            ->first();

        $this->assertSame('"Jan Kowalski" <user@example.com>', $email->getTo()[0]->toString());
        $this->assertStringContainsString(
            'Anulowanie rezerwacji - Joga ze środy 16.07, o 10:00',
            (string) $email->getSubject()
        );

        $body = (string) ($email->getHtmlBody() ?? $email->getTextBody());

        $this->assertStringContainsString('Cześć Jan', $body);
        $this->assertStringContainsString('Twoja rezerwacja na zajęcia Joga ze środy 16.07, o 10:00', $body);
        $this->assertStringContainsString('z powodu nieotrzymania płatności w wyznaczonym czasie', $body);
    }
}
