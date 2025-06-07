<?php

namespace App\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\SaveTransfer;
use App\Entity\Transfer;
use DirectoryTree\ImapEngine\Message;
use Symfony\Component\Clock\Clock;

interface IncomingNotificationMailQuery
{
    public function __invoke(): iterable;
}