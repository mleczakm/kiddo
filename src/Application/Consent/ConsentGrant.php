<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\ConsentType;

final readonly class ConsentGrant
{
    public function __construct(
        public ConsentType $type,
        public ConsentEvidence $evidence,
    ) {}
}
