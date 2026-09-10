<?php

declare(strict_types=1);

namespace App\Application\Service\Waitlist;

use App\Entity\WaitlistEntry;

final readonly class WaitlistJoinResult
{
    public function __construct(
        public WaitlistJoinOutcome $outcome,
        public ?WaitlistEntry $entry = null,
        public int $position = 0,
    ) {}
}
