<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\DummyCommand;

class DummyCommandHandler
{
    public function __invoke(DummyCommand $dummyCommand): void
    {
        // Simulate some work
        sleep(2);
    }
}
