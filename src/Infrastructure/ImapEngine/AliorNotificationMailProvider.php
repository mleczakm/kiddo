<?php

declare(strict_types=1);

namespace App\Infrastructure\ImapEngine;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\MessageQueryInterface;

final readonly class AliorNotificationMailProvider implements IncomingNotificationMailQuery
{
    public function __construct(
        private MailboxInterface $mailbox,
    ) {}

    /**
     * @return iterable<MessageQueryInterface>
     */
    public function __invoke(): iterable
    {
        try {
            yield from @$this->mailbox->inbox()
                ->messages()
                ->from('powiadomienia@alior.pl')
                ->withHeaders()
                ->withBody()
                ->unseen()
                ->get();
        } catch (\Throwable) {
        }
    }
}
