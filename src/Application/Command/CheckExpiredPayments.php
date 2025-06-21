<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class CheckExpiredPayments
{
    public function __construct(
        public int $expirationMinutes = 30
    ) {}
}
