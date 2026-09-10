<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Uid\Ulid;

/**
 * A seat freed up on a lesson — offer it to the head of that lesson's waitlist.
 * Idempotent: re-offers only as many seats as are actually free and unheld.
 */
readonly class OfferWaitlistSeats
{
    public function __construct(
        public Ulid $lessonId,
    ) {}
}
