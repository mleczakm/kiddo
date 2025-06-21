<?php

declare(strict_types=1);

namespace App\Application\Command;

readonly class CheckExpiredBookings
{
    public function __construct(
        public \DateTimeImmutable $expirationTime = new \DateTimeImmutable('-30 minutes')
    ) {}
}
