<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendBookingCancellationNotificationCommand;
use App\Application\CommandHandler\Notification\SendBookingCancellationNotificationHandler;
use App\Entity\Notification;
use App\Entity\NotificationSeverity;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
class SendBookingCancellationNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsCancellationNotificationWithCorrectContent(): void
    {
        $date = new DateTimeImmutable('2025-07-16 10:00:00');
        $user = UserAssembler::new()->withEmail('user@example.com')->withName('Jan Kowalski')->assemble();

        $admin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($admin);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Joga')->assemble())
            ->withSchedule($date)
            ->assemble();

        $em->persist($lesson);

        $booking = BookingAssembler::new()->withUser($user)->withLessons($lesson)->withStatus('cancelled')->assemble();

        $lesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        $handler = self::getContainer()->get(SendBookingCancellationNotificationHandler::class);
        $handler(new SendBookingCancellationNotificationCommand($booking));

        $this->assertCount(2, $this->mailer()->sentEmails());

        // Verify user email
        $userEmail = null;
        $adminEmail = null;
        foreach ($this->mailer()->sentEmails()->all() as $email) {
            foreach ($email->getTo() as $to) {
                if ($to->getAddress() === 'user@example.com') {
                    $userEmail = $email;
                }
                if ($to->getAddress() === 'admin@example.com') {
                    $adminEmail = $email;
                }
            }
        }

        $this->assertNotNull($userEmail);
        $this->assertStringContainsString(
            'Anulowanie rezerwacji - Joga ze środy 16.07, o 10:00',
            (string) $userEmail->getSubject(),
        );

        $body = (string) ($userEmail->getHtmlBody() ?? $userEmail->getTextBody());
        $this->assertStringContainsString('Cześć Jan', $body);
        $this->assertStringContainsString('Twoja rezerwacja na zajęcia Joga ze środy 16.07, o 10:00', $body);

        // Verify admin email
        $this->assertNotNull($adminEmail);
        $this->assertStringContainsString(
            'Rezerwacja anulowana (brak wpłaty) - Jan Kowalski - ze środy 16.07, o 10:00',
            (string) $adminEmail->getSubject(),
        );

        $adminBody = (string) ($adminEmail->getHtmlBody() ?? $adminEmail->getTextBody());
        $this->assertStringContainsString(
            'Rezerwacja użytkownika Jan Kowalski (user@example.com) na zajęcia Joga w dniu ze środy 16.07, o 10:00 została automatycznie anulowana',
            $adminBody,
        );

        // Verify user in-app notification
        $userNotifications = $em->getRepository(Notification::class)->findBy([
            'user' => $user,
        ]);
        self::assertCount(1, $userNotifications);
        self::assertSame('Rezerwacja anulowana', $userNotifications[0]->getTitle());
        self::assertSame(NotificationSeverity::Warning, $userNotifications[0]->getSeverity());

        // Verify admin in-app notification
        $adminNotifications = $em->getRepository(Notification::class)->findBy([
            'user' => $admin,
        ]);
        self::assertCount(1, $adminNotifications);
        self::assertSame('Rezerwacja anulowana (brak wpłaty)', $adminNotifications[0]->getTitle());
        self::assertSame(NotificationSeverity::Warning, $adminNotifications[0]->getSeverity());
        self::assertNotNull($adminNotifications[0]->getBody());
        self::assertStringContainsString('user@example.com', $adminNotifications[0]->getBody());
    }
}
