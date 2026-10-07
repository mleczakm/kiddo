<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\NewUser;
use App\Application\CommandHandler\Notification\NewUserHandler;
use App\Entity\Notification;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
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
        $user = UserAssembler::new()->withEmail('user@example.com')->withRoles('ROLE_USER')->assemble();
        $admin1 = UserAssembler::new()->withEmail('admin1@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $admin2 = UserAssembler::new()->withEmail('admin2@example.com')->withRoles('ROLE_ADMIN')->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->persist($admin1);
        $em->persist($admin2);
        $em->flush();

        $handler = self::getContainer()->get(NewUserHandler::class);

        // Act
        $handler(new NewUser($user));

        // Assert
        $this->mailer()->assertSentEmailCount(3);

        $userId = $user->getId();
        $em->clear();
        $persistedUser = $em->find(User::class, $userId);
        static::assertNotNull($persistedUser);
        static::assertNotEmpty($persistedUser->getConfirmedAt());

        $emails = $this->mailer()->sentEmails();

        $recipients = array_map(static fn(TestEmail $email) => $email->getTo()[0]->getAddress(), $emails->all());
        static::assertContains('user@example.com', $recipients);
        static::assertContains('admin1@example.com', $recipients);
        static::assertContains('admin2@example.com', $recipients);

        // Assert admin email content contains user data
        $userEmail = $persistedUser->getEmail();
        $userName = $persistedUser->getName();
        foreach ($emails as $email) {
            $to = $email->getTo()[0]->getAddress();
            if (in_array($to, ['admin1@example.com', 'admin2@example.com'], true)) {
                $body = (string) ($email->getHtmlBody() ?? $email->getTextBody());
                static::assertStringContainsString((string) $userId, $body);
                static::assertStringContainsString($userEmail, $body);
                static::assertStringContainsString($userName, $body);
            }
        }

        $notifications = $em->getRepository(Notification::class)->findAll();
        static::assertCount(3, $notifications);
    }

    public function testDoesNotEmailAdminsWhoOptedOutOfOperationalEmails(): void
    {
        // Arrange
        $user = UserAssembler::new()->withEmail('user@example.com')->withRoles('ROLE_USER')->assemble();
        $admin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $superAdmin = UserAssembler::new()
            ->withEmail('super@example.com')
            ->withRoles('ROLE_ADMIN', 'ROLE_SUPER_ADMIN')
            ->assemble()
            ->setOperationalEmails(false);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->persist($admin);
        $em->persist($superAdmin);
        $em->flush();

        /** @var NewUserHandler $handler */
        $handler = self::getContainer()->get(NewUserHandler::class);

        // Act
        $handler(new NewUser($user));

        // Assert
        $recipients = array_map(
            static fn(TestEmail $email) => $email->getTo()[0]->getAddress(),
            $this->mailer()->sentEmails()->all(),
        );
        static::assertContains('admin@example.com', $recipients);
        static::assertNotContains('super@example.com', $recipients);
        static::assertCount(
            0,
            $em->getRepository(Notification::class)->findBy(['user' => $superAdmin]),
            'opted-out admin gets no in-app notification either',
        );
    }

    public function testDoesNotSendEmailsIfUserAlreadyConfirmed(): void
    {
        // Arrange
        $user = UserAssembler::new()->withEmail('user@example.com')->withRoles('ROLE_USER')->assemble();
        $admin1 = UserAssembler::new()->withEmail('admin1@example.com')->withRoles('ROLE_ADMIN')->assemble();

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
        $this->mailer()->assertSentEmailCount(0);
    }
}
