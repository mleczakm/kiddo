<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\AdjustmentType;
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
    /**
     * @param list<PricingRule> $candidateRules Every rule that *might* apply -
     *   scope, validity and usage-limit filtering happens inside this method.
     * @param array<string, int> $usageCountsByRuleId Keyed by PricingRule::$id
     *   (as a string); how many times each rule has already been used
     *   globally. No usage-tracking infrastructure exists yet (Stage 12), so
     *   this is empty in practice until then.
     * @param array<string, int> $userUsageCountsByRuleId Same, scoped to the
     *   current user.
     */
    public function quote(
        PricingContext $context,
        int $basePriceMinor,
        string $currency,
        array $candidateRules,
        array $usageCountsByRuleId = [],
        array $userUsageCountsByRuleId = [],
    ): PriceQuote {
        $accumulator = new PricingQuoteAccumulator($basePriceMinor);

        [$nonPromoRules, $promoRules] = $this->partitionApplicableRules(
            $candidateRules,
            $context,
            $usageCountsByRuleId,
            $userUsageCountsByRuleId,
            $accumulator,
        );

        // Step 2.
        $this->applySetPriceRule($nonPromoRules, $accumulator);
        // Steps 3 + 4.
        $this->applyStackableDiscounts($nonPromoRules, $accumulator);
        // Non-stackable fixed/percentage rules still need an exclusivity check.
        $this->applyExclusiveDiscounts($nonPromoRules, $accumulator);
        // Step 5.
        $this->applyPromotionRules($promoRules, $accumulator);

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
     * Splits candidates into non-promo/promo rules that apply to $context right
     * now, recording a rejection reason for every one that doesn't.
     *
     * @param list<PricingRule> $candidateRules
     * @param array<string, int> $usageCountsByRuleId
     * @param array<string, int> $userUsageCountsByRuleId
     * @return array{0: list<PricingRule>, 1: list<PricingRule>}
     */
    private function partitionApplicableRules(
        array $candidateRules,
        PricingContext $context,
        array $usageCountsByRuleId,
        array $userUsageCountsByRuleId,
        PricingQuoteAccumulator $accumulator,
    ): array {
        $nonPromoRules = [];
        $promoRules = [];

        foreach ($candidateRules as $rule) {
            $id = (string) $rule->id;
            $applies = $rule->appliesTo($context, $usageCountsByRuleId[$id] ?? 0, $userUsageCountsByRuleId[$id] ?? 0);

            if (!$applies) {
                $accumulator->reject(sprintf(
                    '%s: does not apply to this context (scope, validity window, or usage limit)',
                    $rule->name,
                ));
                continue;
            }

            if ($rule->promotionCode === null) {
                $nonPromoRules[] = $rule;
                continue;
            }

            $promoRules[] = $rule;
        }

        return [$nonPromoRules, $promoRules];
    }

    /**
     * @param list<PricingRule> $rules
     */
    private function applySetPriceRule(array $rules, PricingQuoteAccumulator $accumulator): void
    {
        $candidates = array_values(array_filter(
            $rules,
            static fn(PricingRule $r): bool => $r->adjustmentType === AdjustmentType::SET_PRICE,
        ));
        usort(
            $candidates,
            static fn(PricingRule $a, PricingRule $b): int => (
                [$b->specificity(), $b->priority] <=> [$a->specificity(), $a->priority]
            ),
        );

        $selected = $candidates[0] ?? null;
        if ($selected === null) {
            return;
        }

        $accumulator->apply($selected);
        foreach (array_slice($candidates, 1) as $rule) {
            $accumulator->reject(sprintf(
                '%s: superseded by a more specific/higher-priority SET_PRICE rule',
                $rule->name,
            ));
        }
    }

    /**
     * @param list<PricingRule> $rules
     */
    private function applyStackableDiscounts(array $rules, PricingQuoteAccumulator $accumulator): void
    {
        foreach ([AdjustmentType::FIXED_AMOUNT_OFF, AdjustmentType::PERCENTAGE_OFF] as $type) {
            $candidates = array_values(array_filter(
                $rules,
                static fn(PricingRule $r): bool => $r->adjustmentType === $type && $r->stackable,
            ));
            usort($candidates, static fn(PricingRule $a, PricingRule $b): int => $b->priority <=> $a->priority);

            foreach ($candidates as $rule) {
                if ($accumulator->isBlockedByExclusivity($rule)) {
                    $accumulator->reject(sprintf(
                        '%s: exclusivity group "%s" already consumed',
                        $rule->name,
                        $rule->exclusivityGroup,
                    ));
                    continue;
                }

                $accumulator->apply($rule);
            }
        }
    }

    /**
     * Non-stackable fixed/percentage rules (SET_PRICE already handled in
     * applySetPriceRule()) - still gated by exclusivity, even without another
     * stackable rule competing for the same slot.
     *
     * @param list<PricingRule> $rules
     */
    private function applyExclusiveDiscounts(array $rules, PricingQuoteAccumulator $accumulator): void
    {
        foreach ($rules as $rule) {
            if ($rule->adjustmentType === AdjustmentType::SET_PRICE || $rule->stackable) {
                continue;
            }

            if ($accumulator->isBlockedByExclusivity($rule)) {
                $accumulator->reject(sprintf(
                    '%s: exclusivity group "%s" already consumed',
                    $rule->name,
                    $rule->exclusivityGroup,
                ));
                continue;
            }

            $accumulator->apply($rule);
        }
    }

    /**
     * @param list<PricingRule> $rules
     */
    private function applyPromotionRules(array $rules, PricingQuoteAccumulator $accumulator): void
    {
        foreach ($rules as $rule) {
            if ($accumulator->isBlockedByExclusivity($rule)) {
                $accumulator->reject(sprintf(
                    '%s: exclusivity group "%s" already consumed',
                    $rule->name,
                    $rule->exclusivityGroup,
                ));
                continue;
            }

            $accumulator->apply($rule);
        }
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
        $parts = [
            (string) $context->userId,
            (string) $context->lessonId,
            (string) $context->seriesId,
            $context->ticketType,
            $context->promotionCode ?? '',
            $context->evaluationTime->format(\DateTimeInterface::ATOM),
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
