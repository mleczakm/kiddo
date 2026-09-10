<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;

final readonly class RevokeConsent
{
    public function __construct(
        public User $user,
        public ConsentType $type,
        public ConsentSource $source,
    ) {}
}
