<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use DirectoryTree\ImapEngine\MessageQueryInterface;
use Symfony\Component\Lock\LockFactory;

final readonly class IncomingNotificationMailQueryReentrancyGuardDecorator implements IncomingNotificationMailQuery
{
    private const string LOCK_RESOURCE = 'incoming-notification-mail-query';

    public function __construct(
        private IncomingNotificationMailQuery $decorated,
        private LockFactory $lockFactory,
    ) {}

    /**
     * ImportTransfersFromMail is scheduled every 30s. If one run takes longer than that
     * (slow IMAP round-trip, Gmail throttling), the next dispatch can start on the same
     * task worker while the previous is still in flight. A process-local boolean is not
     * sufficient: task workers are separate processes, and coroutine service pooling may
     * also provide separate decorator instances. The shared semaphore serializes every
     * task worker before it touches IMAP, preventing both concurrent access to one Mailbox
     * stream and two workers importing the same still-unseen message.
     *
     * @return iterable<MessageQueryInterface>
     */
    #[\Override]
    public function __invoke(): iterable
    {
        $lock = $this->lockFactory->createLock(self::LOCK_RESOURCE);

        if (!$lock->acquire()) {
            return;
        }

        try {
            yield from ($this->decorated)();
        } finally {
            $lock->release();
        }
    }
}
