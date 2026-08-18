<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\ImapEngine;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use DirectoryTree\ImapEngine\MailboxInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class AliorNotificationMailProviderFunctionalTest extends KernelTestCase
{
    private IncomingNotificationMailQuery $provider;

    private MailboxInterface $mailbox;

    private LoggerInterface $logger;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->provider = self::getContainer()->get(IncomingNotificationMailQuery::class);
        $this->mailbox = self::getContainer()->get(MailboxInterface::class);
        $this->logger = self::getContainer()->get(LoggerInterface::class);
    }

    public function testProviderCanBeInstantiated(): void
    {
        static::assertInstanceOf(IncomingNotificationMailQuery::class, $this->provider);
    }

    public function testProviderHasRequiredDependencies(): void
    {
        static::assertInstanceOf(MailboxInterface::class, $this->mailbox);
        static::assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    public function testProviderCanBeInvokedWithoutThrowing(): void
    {
        // The provider should be able to be invoked without throwing exceptions
        // even if there are no messages or connection issues
        $this->expectNotToPerformAssertions();
        iterator_to_array(($this->provider)());
    }
}
