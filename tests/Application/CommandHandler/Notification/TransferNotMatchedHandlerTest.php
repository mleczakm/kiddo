<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use PHPUnit\Framework\Attributes\Group;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Application\CommandHandler\Notification\TransferNotMatchedHandler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
class TransferNotMatchedHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    private TransferNotMatchedHandler $handler;

    private CacheItemPoolInterface $cache;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->handler = $container->get(TransferNotMatchedHandler::class);
        $this->cache = $container->get('cache.app');

        // Clear any existing cache
        $this->cache->clear();
    }

    public function testSendsNotificationToAdminsWhenTransferNotMatched(): void
    {
        // Create test admin users
        $admin1 = UserAssembler::new()
            ->withEmail('admin1@example.com')
            ->withName('Admin One')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $admin2 = UserAssembler::new()
            ->withEmail('admin2@example.com')
            ->withName('Admin Two')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        // Create a test transfer
        $transfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('John Doe')
            ->withTitle('Test transfer')
            ->withAmount('100.00')
            ->withTransferredAt(new \DateTimeImmutable('2025-01-01 12:00:00'))
            ->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($admin1);
        $em->persist($admin2);
        $em->persist($transfer);
        $em->flush();

        // Create and handle the command
        $command = new TransferNotMatchedCommand($transfer);
        ($this->handler)($command);

        // Assert emails were sent to all admins
        $this->assertEmailCount(2);

        $emails = $this->mailer()
            ->sentEmails();

        $recipients = array_map(fn($email) => $email->getTo()[0]->toString(), $emails->all());

        $this->assertContains('"Admin One" <admin1@example.com>', $recipients);
        $this->assertContains('"Admin Two" <admin2@example.com>', $recipients);

        // Assert email content
        $email = $emails->first();
        $email->assertSubject('Nie znaleziono dopasowania dla przelewu');
        $email->assertContains(
            'Otrzymaliśmy nowy przelew, którego nie udało się automatycznie dopasować do żadnej płatności'
        );
        $email->assertContains('John Doe');
        $email->assertContains('100.00');
        $email->assertContains('2025-01-01 12:00');

        // Test caching - should not send another notification
        $this->mailer()
            ->reset();
        ($this->handler)($command);
        $this->assertCount(0, $this->mailer()->sentEmails()->all());
    }

    public function testDoesNotSendNotificationWhenNoAdminsExist(): void
    {
        // Create a test transfer
        $transfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('John Doe')
            ->withTitle('Test transfer')
            ->withAmount('100.00')
            ->withTransferredAt(new \DateTimeImmutable('2025-01-01 12:00:00'))
            ->assemble();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($transfer);
        $em->flush();

        // Create and handle the command
        $command = new TransferNotMatchedCommand($transfer);
        ($this->handler)($command);

        // Assert no emails were sent
        $this->assertEmailCount(0);
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cache->clear();
    }
}
