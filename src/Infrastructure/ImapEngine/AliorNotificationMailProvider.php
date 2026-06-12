<?php

declare(strict_types=1);

namespace App\Infrastructure\ImapEngine;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use App\Infrastructure\Swoole\CurrentWorkerRestarterInterface;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use Psr\Log\LoggerInterface;

final readonly class AliorNotificationMailProvider implements IncomingNotificationMailQuery
{
    public function __construct(
        private MailboxInterface $mailbox,
        private CurrentWorkerRestarterInterface $workerRestarter,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return iterable<MessageQueryInterface>
     */
    public function __invoke(): iterable
    {
        try {
            $this->mailbox->reconnect();

            yield from $this->mailbox
                ->inbox()
                ->messages()
                ->from('powiadomienia@alior.pl')
                ->withHeaders()
                ->withBody()
                ->unseen()
                ->get();
        } catch (\Throwable $exception) {
            $this->logger->error('Gmail IMAP query failed, restarting worker', [
                'exception' => $exception,
            ]);

            $this->workerRestarter->restart();
        }
    }
}
