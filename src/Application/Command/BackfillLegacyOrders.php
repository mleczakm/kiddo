<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class BackfillLegacyOrders
{
    public function __construct(
        public int $limit = 100,
    ) {}
}
