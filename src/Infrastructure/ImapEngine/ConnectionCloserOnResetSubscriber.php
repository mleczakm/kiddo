<?php

declare(strict_types=1);

namespace App\Infrastructure\ImapEngine;

use DirectoryTree\ImapEngine\MailboxInterface;
use Symfony\Contracts\Service\ResetInterface;

final readonly class ConnectionCloserOnResetSubscriber implements ResetInterface
{
    public function __construct(
        private MailboxInterface $mailbox
    ) {}

    public function reset(): void
    {
        $this->mailbox->disconnect();
    }
}
