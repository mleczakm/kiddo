<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingContext;
use App\Domain\Commerce\Pricing\PricingRule;

final readonly class PricingAdjustments
{
    public function __construct(
        private ApplicablePricingRules $applicableRules = new ApplicablePricingRules(),
    ) {}

    /**
     * @param list<PricingRule> $candidateRules
     */
    public function apply(
        array $candidateRules,
        PricingContext $context,
        PricingRuleUsage $usage,
        PricingQuoteAccumulator $accumulator,
    ): void {
        [$nonPromoRules, $promoRules] = $this->applicableRules->partition(
            $candidateRules,
            $context,
            $usage,
            $accumulator,
        );

        $this->applySetPriceRule($nonPromoRules, $accumulator);
        $this->applyStackableDiscounts($nonPromoRules, $accumulator);
        $this->applyExclusiveDiscounts($nonPromoRules, $accumulator);
        foreach ($promoRules as $rule) {
            $accumulator->applyUnlessExclusive($rule);
        }
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
                $accumulator->applyUnlessExclusive($rule);
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

            $accumulator->applyUnlessExclusive($rule);
        }
    }
}
