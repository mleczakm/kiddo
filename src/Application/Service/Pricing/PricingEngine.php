<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\PriceQuote;
use App\Domain\Commerce\Pricing\PricingContext;
use App\Domain\Commerce\Pricing\PricingRule;

/**
 * Resolves a final price from a base price and a set of candidate pricing
 * rules (Stage 7 of the commerce rollout plan). Pure - no persistence, no
 * side effects; the caller decides where the candidate rules and base price
 * come from, and what (if anything) to do with the result. Each private
 * method below corresponds to one step of the plan's "Recommended
 * algorithm".
 */
final readonly class PricingEngine
{
    public function __construct(
        private PricingAdjustments $adjustments = new PricingAdjustments(),
    ) {}

    /**
     * @param list<PricingRule> $candidateRules Every rule that *might* apply -
     *   scope, validity and usage-limit filtering happens inside this method.
     */
    public function quote(
        PricingContext $context,
        int $basePriceMinor,
        string $currency,
        array $candidateRules,
        PricingRuleUsage $usage = new PricingRuleUsage(),
    ): PriceQuote {
        $accumulator = new PricingQuoteAccumulator($basePriceMinor);

        $this->adjustments->apply($candidateRules, $context, $usage, $accumulator);

        // Step 6: floor at zero (Accumulator::apply() already clamps after every step).
        $finalPriceMinor = max(0, $accumulator->priceMinor());

        // Step 7 is implicit: $accumulator has been recording adjustments/rejections throughout.
        // Step 8.
        $quoteHash = $this->hash($context, $basePriceMinor, $finalPriceMinor, $currency, $accumulator->adjustments());

        return new PriceQuote(
            basePriceMinor: $basePriceMinor,
            finalPriceMinor: $finalPriceMinor,
            currency: $currency,
            adjustments: $accumulator->adjustments(),
            rejectedRuleReasons: $accumulator->rejectedReasons(),
            quoteHash: $quoteHash,
            computedAt: $context->evaluationTime,
        );
    }

    /**
     * @param list<\App\Domain\Commerce\Pricing\PriceAdjustment> $adjustments
     */
    private function hash(
        PricingContext $context,
        int $basePriceMinor,
        int $finalPriceMinor,
        string $currency,
        array $adjustments,
    ): string {
        // evaluationTime decides which rules apply, but is not itself part of
        // the quote identity. The modal and checkout necessarily calculate in
        // separate requests; including the current second would make every
        // unchanged quote look stale. A real time-boundary change still alters
        // the applied adjustments/final price below and therefore the hash.
        $parts = [
            (string) $context->userId,
            (string) $context->lessonId,
            (string) $context->seriesId,
            $context->ticketType,
            $context->promotionCode ?? '',
            (string) $basePriceMinor,
            (string) $finalPriceMinor,
            $currency,
        ];

        foreach ($adjustments as $adjustment) {
            $parts[] = $adjustment->ruleId . ':' . $adjustment->deltaMinor;
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }
}
