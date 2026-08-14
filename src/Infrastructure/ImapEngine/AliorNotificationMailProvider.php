<?php

declare(strict_types=1);

namespace App\Infrastructure\ImapEngine;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use Psr\Log\LoggerInterface;

final readonly class AliorNotificationMailProvider implements IncomingNotificationMailQuery
{
    public function __construct(
        private MailboxInterface $mailbox,
        private LoggerInterface $logger,
        private string $mailboxUsername,
        private string $mailboxPassword,
    ) {}

    /**
     * @return iterable<MessageQueryInterface>
     */
    public function __invoke(): iterable
    {
        if ($this->mailboxUsername === '' || $this->mailboxPassword === '') {
            $this->logger->info('Gmail IMAP skipped: mailbox credentials are not configured');

            return;
        }

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
            // Deliberately doesn't restart the worker (previously called
            // CurrentWorkerRestarterInterface::restart(), i.e. $server->stop($server->worker_id)).
            // A one-off Gmail hiccup here is normal and self-heals on the next scheduled run 30s
            // later - reconnect() above always tears down and rebuilds the connection from
            // scratch regardless of prior state, so nothing needs a restart to recover. Explicitly
            // stopping the worker raced against Swoole's own process management (observed as
            // "Server::stop_async_worker(): failed to push WORKER_STOP message, Error: No such
            // process"), corrupting a task-worker slot: for hours afterward, roughly half of
            // every message the scheduler's master-process tick dispatched (measured via
            // "Sending message" vs "Received message" log counts) was silently dropped, eventually
            // exhausting file descriptors and OOM-killing the whole container. A brief, external,
            // self-recovering IMAP hiccup should never risk that.
            $this->logger->error('Gmail IMAP query failed', [
                'exception' => $exception,
            ]);
        }
    }
}
