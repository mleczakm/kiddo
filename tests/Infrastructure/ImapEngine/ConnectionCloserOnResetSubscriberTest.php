<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use App\Infrastructure\ImapEngine\ConnectionCloserOnResetSubscriber;
use DirectoryTree\ImapEngine\MailboxInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class ConnectionCloserOnResetSubscriberTest extends TestCase
{
    public function testCloseConnectionOnReset(): void
    {
        $mockMailbox = $this->createMock(MailboxInterface::class);
        $mockMailbox->expects($this->once())->method('disconnect');

        $subscriber = new ConnectionCloserOnResetSubscriber($mockMailbox);
        $subscriber->reset();
    }
}
