<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use App\Infrastructure\ImapEngine\AliorNotificationMailProvider;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
class AliorNotificationMailProviderTest extends TestCase
{
    public function testSkipsImapWhenCredentialsAreMissing(): void
    {
        $mailbox = $this->createMock(FakeMailbox::class);
        $mailbox->expects($this->never())->method('reconnect');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Gmail IMAP skipped: mailbox credentials are not configured');

        $provider = new AliorNotificationMailProvider($mailbox, $logger, mailboxUsername: '', mailboxPassword: '');

        static::assertSame([], iterator_to_array($provider()));
    }

    public function testLogsErrorAndDoesNotThrowOnThrowableWhenCredentialsConfigured(): void
    {
        // Deliberately no worker restart on failure - see AliorNotificationMailProvider's
        // catch block for why. A one-off failure here just logs and lets the next
        // scheduled run 30s later retry via reconnect().
        $testMailbox = new FakeMailbox(folders: [new ThrowingFolder('inbox')]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Gmail IMAP query failed', static::arrayHasKey('exception'));

        $provider = new AliorNotificationMailProvider(
            $testMailbox,
            $logger,
            mailboxUsername: 'user@example.com',
            mailboxPassword: 'secret',
        );

        static::assertSame([], iterator_to_array($provider()));
    }
}

class ThrowingFolder extends FakeFolder
{
    #[\Override]
    public function messages(): MessageQueryInterface
    {
        throw new \ErrorException('Simulated exception');
    }
}
