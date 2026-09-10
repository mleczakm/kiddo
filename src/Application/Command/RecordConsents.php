<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Consent\ConsentGrant;
use App\Entity\ConsentSource;
use App\Entity\User;

final readonly class RecordConsents
{
    /** @param list<ConsentGrant> $grants */
    public function __construct(
        public User $user,
        public ConsentSource $source,
        public array $grants,
    ) {}
}
