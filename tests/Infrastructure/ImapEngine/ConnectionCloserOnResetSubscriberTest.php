<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use PHPUnit\Framework\Attributes\Group;
use DirectoryTree\ImapEngine\MailboxInterface;
use App\Infrastructure\ImapEngine\ConnectionCloserOnResetSubscriber;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class ConnectionCloserOnResetSubscriberTest extends TestCase
{
    public function testCloseConnectionOnReset(): void
    {
        $mockMailbox = $this->createMock(MailboxInterface::class);
        $mockMailbox->expects($this->once())
            ->method('disconnect');

        $subscriber = new ConnectionCloserOnResetSubscriber($mockMailbox);
        $subscriber->reset();
    }
}
