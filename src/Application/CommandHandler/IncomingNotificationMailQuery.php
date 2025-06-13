<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use DirectoryTree\ImapEngine\MessageQueryInterface;

interface IncomingNotificationMailQuery
{
    /**
     * @return iterable<MessageQueryInterface>
     */
    public function __invoke(): iterable;
}
