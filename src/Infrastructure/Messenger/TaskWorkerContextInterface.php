<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

interface TaskWorkerContextInterface
{
    public function isInTaskWorker(): bool;
}
