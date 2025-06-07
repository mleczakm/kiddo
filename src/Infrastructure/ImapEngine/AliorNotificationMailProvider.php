<?php

namespace App\Infrastructure\ImapEngine;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use DirectoryTree\ImapEngine\MailboxInterface;

readonly final class AliorNotificationMailProvider implements IncomingNotificationMailQuery
{
    public function __construct(
        private MailboxInterface $mailbox,
    ) {}
    public function __invoke(): iterable
    {
        try {
            yield from $this->mailbox->inbox()->messages()->from('powiadomienia@alior.pl')->withHeaders()
                ->withBody()
                ->unseen()
                ->get();
        } catch (\Throwable $exception) {
            return [];
        }
    }
}