<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use DirectoryTree\ImapEngine\MessageInterface;

interface IncomingNotificationMailQuery
{
    /**
     * @return iterable<MessageInterface>
     */
    public function __invoke(): iterable;
}
