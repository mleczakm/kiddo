<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\ImapEngine;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use App\Infrastructure\Swoole\CurrentWorkerRestarterInterface;
use DirectoryTree\ImapEngine\MailboxInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class AliorNotificationMailProviderFunctionalTest extends KernelTestCase
{
    private IncomingNotificationMailQuery $provider;

    private MailboxInterface $mailbox;

    private CurrentWorkerRestarterInterface $workerRestarter;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->provider = self::getContainer()->get(IncomingNotificationMailQuery::class);
        $this->mailbox = self::getContainer()->get(MailboxInterface::class);
        $this->workerRestarter = self::getContainer()->get(CurrentWorkerRestarterInterface::class);
        $this->logger = self::getContainer()->get(LoggerInterface::class);
    }

    public function testProviderCanBeInstantiated(): void
    {
        $this->assertInstanceOf(IncomingNotificationMailQuery::class, $this->provider);
    }

    public function testProviderHasRequiredDependencies(): void
    {
        $this->assertInstanceOf(MailboxInterface::class, $this->mailbox);
        $this->assertInstanceOf(CurrentWorkerRestarterInterface::class, $this->workerRestarter);
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    public function testProviderCanBeInvokedWithoutThrowing(): void
    {
        // The provider should be able to be invoked without throwing exceptions
        // even if there are no messages or connection issues
        $this->expectNotToPerformAssertions();
        iterator_to_array(($this->provider)());
    }
}
