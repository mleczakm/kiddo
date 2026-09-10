<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Sweep waitlist offers whose {@see \App\Entity\WaitlistEntry::OFFER_TTL_MINUTES}
 * hold has elapsed: mark them expired and re-offer the seat to the next in line.
 * Scheduled every few minutes from {@see \App\Infrastructure\Symfony\Scheduler\MainSchedule}.
 */
readonly class ExpireWaitlistOffers
{
    public function __construct(
        public \DateTimeImmutable $referenceTime = new \DateTimeImmutable(),
    ) {}
}
