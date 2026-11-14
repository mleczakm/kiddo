<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

final readonly class PricingRuleUsage
{
    /**
     * @param array<string, int> $totalByRule
     * @param array<string, int> $customerByRule
     */
    public function __construct(
        public array $totalByRule = [],
        public array $customerByRule = [],
    ) {}
}
