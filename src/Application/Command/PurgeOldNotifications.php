<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class PurgeOldNotifications
{
    public function __construct(
        public string $olderThan = '2 months',
    ) {}
}
