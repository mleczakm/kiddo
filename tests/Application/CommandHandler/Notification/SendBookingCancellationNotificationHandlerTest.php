<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendBookingCancellationNotificationCommand;
use App\Application\CommandHandler\Notification\SendBookingCancellationNotificationHandler;
use App\Entity\FinanceContact;
use App\Entity\Notification;
use App\Entity\NotificationSeverity;
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
class SendBookingCancellationNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testSendsCancellationNotificationWithCorrectContent(): void
    {
        $date = new DateTimeImmutable('2025-07-16 10:00:00');
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

        $booking = BookingAssembler::new()->withUser($user)->withLessons($lesson)->withStatus('cancelled')->assemble();

        $lesson->addBooking($booking);
        $em->persist($booking);
        $em->flush();

        $handler = self::getContainer()->get(SendBookingCancellationNotificationHandler::class);
        $handler(new SendBookingCancellationNotificationCommand($booking->getId()));

        static::assertCount(3, $this->mailer()->sentEmails());

        // Verify user email
        $userEmail = null;
        $financeEmail = null;
        $hostEmail = null;
        foreach ($this->mailer()->sentEmails()->all() as $email) {
            foreach ($email->getTo() as $to) {
                if ($to->getAddress() === 'user@example.com') {
                    $userEmail = $email;
                }
                if ($to->getAddress() === 'finance@example.com') {
                    $financeEmail = $email;
                }
                if ($to->getAddress() === 'host@example.com') {
                    $hostEmail = $email;
                }
            }
        }

        static::assertNotNull($userEmail);
        static::assertStringContainsString(
            'Anulowanie rezerwacji - Joga ze środy 16.07, o 10:00',
            (string) $userEmail->getSubject(),
        );

        $body = (string) ($userEmail->getHtmlBody() ?? $userEmail->getTextBody());
        static::assertStringContainsString('Cześć Jan', $body);
        static::assertStringContainsString('Twoja rezerwacja na zajęcia Joga ze środy 16.07, o 10:00', $body);

        // Verify internal copies
        static::assertNotNull($financeEmail);
        static::assertNotNull($hostEmail);
        static::assertStringContainsString(
            'Rezerwacja anulowana (brak wpłaty) - Jan Kowalski - ze środy 16.07, o 10:00',
            (string) $financeEmail->getSubject(),
        );

        $adminBody = (string) ($financeEmail->getHtmlBody() ?? $financeEmail->getTextBody());
        static::assertStringContainsString(
            'Rezerwacja użytkownika Jan Kowalski (user@example.com) na zajęcia Joga w dniu ze środy 16.07, o 10:00 została automatycznie anulowana',
            $adminBody,
        );
        static::assertCount(0, $this->mailer()->sentEmails()->whereTo($unrelatedAdmin->getEmail()));

        // Verify user in-app notification
        $userNotifications = $em->getRepository(Notification::class)->findBy([
            'user' => $user,
        ]);
        static::assertCount(1, $userNotifications);
        static::assertSame('Rezerwacja anulowana', $userNotifications[0]->getTitle());
        static::assertSame(NotificationSeverity::Warning, $userNotifications[0]->getSeverity());

        // Verify internal in-app notifications
        $financeNotifications = $em->getRepository(Notification::class)->findBy([
            'user' => $finance,
        ]);
        static::assertCount(1, $financeNotifications);
        $financeNotification = $financeNotifications[0];
        static::assertInstanceOf(Notification::class, $financeNotification);
        static::assertSame('Rezerwacja anulowana (brak wpłaty)', $financeNotification->getTitle());
        static::assertSame(NotificationSeverity::Warning, $financeNotification->getSeverity());
        $financeBody = $financeNotification->getBody();
        static::assertNotNull($financeBody);
        static::assertStringContainsString('user@example.com', $financeBody);
        static::assertCount(1, $em->getRepository(Notification::class)->findBy(['user' => $host]));
        static::assertCount(0, $em->getRepository(Notification::class)->findBy(['user' => $unrelatedAdmin]));
    }
}
