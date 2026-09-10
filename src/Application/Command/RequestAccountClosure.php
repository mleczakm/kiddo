<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Account holder asks to close their account. permanent=false is a reversible
 * pause; permanent=true starts the deletion grace period. Either way, logging
 * back in cancels it (until anonymisation runs).
 */
final readonly class RequestAccountClosure
{
    public function __construct(
        public int $userId,
        public bool $permanent,
    ) {}
}
