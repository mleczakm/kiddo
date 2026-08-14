<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use DirectoryTree\ImapEngine\MessageQueryInterface;

final class IncomingNotificationMailQueryReentrancyGuardDecorator implements IncomingNotificationMailQuery
{
    private bool $running = false;

    public function __construct(
        private readonly IncomingNotificationMailQuery $decorated,
    ) {}

    /**
     * ImportTransfersFromMail is scheduled every 30s. If one run takes longer than that
     * (slow IMAP round-trip, Gmail throttling), the next dispatch can start on the same
     * task worker while the previous is still in flight - both coroutines share the same
     * Mailbox connection (a plain singleton, not coroutine-pooled), and one's
     * disconnect()/reconnect() can race an in-flight read on the other, tearing the stream
     * down mid-read. That produces "Unknown stream error. Metadata: []" - ImapConnection's
     * generic fallback for a stream that's already closed by the time it tries to read from
     * it. Skip overlapping runs instead of racing.
     *
     * @return iterable<MessageQueryInterface>
     */
    public function __invoke(): iterable
    {
        if ($this->running) {
            return;
        }

        $this->running = true;

        try {
            yield from ($this->decorated)();
        } finally {
            $this->running = false;
        }
    }
}
