<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

/**
 * One adjustment applied to the base price while resolving a PriceQuote.
 * $deltaMinor is the signed change to the running price (negative for a
 * discount); for SET_PRICE it is the difference between the price before
 * and after the rule was applied, so summing every adjustment's delta
 * against the base price always reproduces the final price.
 */
final readonly class PriceAdjustment
{
    public function __construct(
        public string $ruleId,
        public AdjustmentType $type,
        public int $deltaMinor,
        public string $label,
    ) {}
}
