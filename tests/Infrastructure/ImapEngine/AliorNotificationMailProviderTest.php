<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use App\Infrastructure\ImapEngine\AliorNotificationMailProvider;
use App\Infrastructure\Swoole\CurrentWorkerRestarterInterface;
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
        $mailbox->expects($this->never())
            ->method('reconnect');

        $workerRestarter = $this->createMock(CurrentWorkerRestarterInterface::class);
        $workerRestarter->expects($this->never())
            ->method('restart');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Gmail IMAP skipped: mailbox credentials are not configured');

        $provider = new AliorNotificationMailProvider(
            $mailbox,
            $workerRestarter,
            $logger,
            mailboxUsername: '',
            mailboxPassword: '',
        );

        $this->assertSame([], iterator_to_array($provider()));
    }

    public function testRestartsWorkerOnThrowableWhenCredentialsConfigured(): void
    {
        $testMailbox = new FakeMailbox(folders: [new ThrowingFolder('inbox')]);
        $workerRestarter = $this->createMock(CurrentWorkerRestarterInterface::class);
        $workerRestarter->expects($this->once())
            ->method('restart');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Gmail IMAP query failed, restarting worker', $this->arrayHasKey('exception'));

        $provider = new AliorNotificationMailProvider(
            $testMailbox,
            $workerRestarter,
            $logger,
            mailboxUsername: 'user@example.com',
            mailboxPassword: 'secret',
        );

        $this->assertSame([], iterator_to_array($provider()));
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
