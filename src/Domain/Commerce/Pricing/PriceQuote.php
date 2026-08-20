<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

/**
 * The result of resolving a price via PricingEngine::quote(). $adjustments
 * and $rejectedRuleReasons make the outcome auditable - which rules fired,
 * which didn't and why - without needing to re-run the algorithm.
 */
final readonly class PriceQuote
{
    /**
     * @param list<PriceAdjustment> $adjustments
     * @param list<string> $rejectedRuleReasons
     */
    public function __construct(
        public int $basePriceMinor,
        public int $finalPriceMinor,
        public string $currency,
        public array $adjustments,
        public array $rejectedRuleReasons,
        public string $quoteHash,
        public \DateTimeImmutable $computedAt,
    ) {}
}
