<?php

declare(strict_types=1);

namespace App\Application\Accounting;

final readonly class PlatformBillingSummary
{
    public function __construct(
        public bool $isCurrentMonth,
        public string $monthLabel,
        public float $currentDue,
        public float $pastDue,
        public bool $hasPastDue,
    ) {}
}
