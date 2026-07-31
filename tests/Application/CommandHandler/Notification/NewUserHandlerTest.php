<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Entity\Notification;
use PHPUnit\Framework\Attributes\Group;
use App\Application\Command\Notification\NewUser;
use App\Application\CommandHandler\Notification\NewUserHandler;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\NotificationAssertionsTrait;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Mailer\Test\TestEmail;

#[Group('functional')]
class NewUserHandlerTest extends KernelTestCase
{
    use NotificationAssertionsTrait;
    use InteractsWithMailer;

    public function testSendEmailsToUserAndAdmins(): void
    {
        // Arrange
        $user = UserAssembler::new()
            ->withEmail('user@example.com')
            ->withRoles('ROLE_USER')
            ->assemble();
        $admin1 = UserAssembler::new()
            ->withEmail('admin1@example.com')
            ->withRoles('ROLE_ADMIN')
            ->assemble();
        $admin2 = UserAssembler::new()
            ->withEmail('admin2@example.com')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($admin1);
        $em->persist($admin2);
        $em->flush();

        $handler = self::getContainer()->get(NewUserHandler::class);

        // Act
        $handler(new NewUser($user));

        // Assert
        $this->mailer()
            ->assertSentEmailCount(3);

        $userId = $user->getId();
        $em->clear();
        $persistedUser = $em->find(User::class, $userId);
        self::assertNotNull($persistedUser);
        self::assertNotEmpty($persistedUser->getConfirmedAt());

        $emails = $this->mailer()
            ->sentEmails();

        $recipients = array_map(fn(TestEmail $email) => $email->getTo()[0]->getAddress(), $emails->all());
        $this->assertContains('user@example.com', $recipients);
        $this->assertContains('admin1@example.com', $recipients);
        $this->assertContains('admin2@example.com', $recipients);

        // Assert admin email content contains user data
        $userEmail = $persistedUser->getEmail();
        $userName = $persistedUser->getName();
        foreach ($emails as $email) {
            $to = $email->getTo()[0]
                ->getAddress();
            if (in_array($to, ['admin1@example.com', 'admin2@example.com'], true)) {
                $body = (string) ($email->getHtmlBody() ?? $email->getTextBody());
                $this->assertStringContainsString((string) $userId, $body);
                $this->assertStringContainsString($userEmail, $body);
                $this->assertStringContainsString($userName, $body);
            }
        }

        $notifications = $em->getRepository(Notification::class)->findAll();
        self::assertCount(3, $notifications);
    }

    public function testDoesNotSendEmailsIfUserAlreadyConfirmed(): void
    {
        // Arrange
        $user = UserAssembler::new()
            ->withEmail('user@example.com')
            ->withRoles('ROLE_USER')
            ->assemble();
        $admin1 = UserAssembler::new()
            ->withEmail('admin1@example.com')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($admin1);
        $em->flush();

        // Simulate user already confirmed (e.g., from previous login)
        $user->setConfirmedAt(new \DateTimeImmutable());
        $em->flush();

        $handler = self::getContainer()->get(NewUserHandler::class);

        // Act
        $handler(new NewUser($user));

        // Assert - no emails should be sent
        $this->mailer()
            ->assertSentEmailCount(0);
    }
}
