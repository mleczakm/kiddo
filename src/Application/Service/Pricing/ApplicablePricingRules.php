<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\PricingContext;
use App\Domain\Commerce\Pricing\PricingRule;

final class ApplicablePricingRules
{
    /**
     * Splits candidates into non-promo/promo rules that apply to $context right
     * now, recording a rejection reason for every one that doesn't.
     *
     * @param list<PricingRule> $candidateRules
     * @return array{0: list<PricingRule>, 1: list<PricingRule>}
     */
    public function partition(
        array $candidateRules,
        PricingContext $context,
        PricingRuleUsage $usage,
        PricingQuoteAccumulator $accumulator,
    ): array {
        $nonPromoRules = [];
        $promoRules = [];

        foreach ($candidateRules as $rule) {
            $id = (string) $rule->id;
            $applies = $rule->appliesTo($context, $usage->totalByRule[$id] ?? 0, $usage->customerByRule[$id] ?? 0);

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
}
