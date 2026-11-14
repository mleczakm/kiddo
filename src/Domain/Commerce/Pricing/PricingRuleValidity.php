<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

final readonly class PricingRuleValidity
{
    public function __construct(
        public ?\DateTimeImmutable $from,
        public ?\DateTimeImmutable $until,
    ) {}

    public function includes(\DateTimeImmutable $time): bool
    {
        if ($this->from !== null && $time < $this->from) {
            return false;
        }

        return $this->until === null || $time <= $this->until;
    }
}
