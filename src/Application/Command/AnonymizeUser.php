<?php

declare(strict_types=1);

namespace App\Application\Command;

/** Irreversibly anonymise one account whose deletion grace period has elapsed. */
final readonly class AnonymizeUser
{
    public function __construct(
        public int $userId,
    ) {}
}
