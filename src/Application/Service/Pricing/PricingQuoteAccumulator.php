<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PriceAdjustment;
use App\Domain\Commerce\Pricing\PricingRule;

/**
 * @internal mutable working state for PricingEngine::quote() - not part of
 * the pricing engine's public API, and never exposed outside this class.
 */
final class PricingQuoteAccumulator
{
    /** @var list<PriceAdjustment> */
    private array $adjustments = [];

    /** @var list<string> */
    private array $rejectedReasons = [];

    /** @var array<string, true> */
    private array $consumedExclusivityGroups = [];

    public function __construct(
        private int $priceMinor,
    ) {}

    public function priceMinor(): int
    {
        return $this->priceMinor;
    }

    public function isBlockedByExclusivity(PricingRule $rule): bool
    {
        return (
            $rule->exclusivityGroup !== null
            && array_key_exists($rule->exclusivityGroup, $this->consumedExclusivityGroups)
        );
    }

    public function apply(PricingRule $rule): void
    {
        $before = $this->priceMinor;
        $this->priceMinor = max(0, match ($rule->adjustmentType) {
            AdjustmentType::SET_PRICE => $rule->adjustmentValue,
            AdjustmentType::FIXED_AMOUNT_OFF => $this->priceMinor - $rule->adjustmentValue,
            AdjustmentType::PERCENTAGE_OFF => $this->priceMinor
                - (int) round(($this->priceMinor * $rule->adjustmentValue) / 100),
        });

        $this->adjustments[] = new PriceAdjustment(
            ruleId: (string) $rule->id,
            type: $rule->adjustmentType,
            deltaMinor: $this->priceMinor - $before,
            label: $rule->name,
        );

        if ($rule->exclusivityGroup !== null) {
            $this->consumedExclusivityGroups[$rule->exclusivityGroup] = true;
        }
    }

    public function reject(string $reason): void
    {
        $this->rejectedReasons[] = $reason;
    }

    /**
     * @return list<PriceAdjustment>
     */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    /**
     * @return list<string>
     */
    public function rejectedReasons(): array
    {
        return $this->rejectedReasons;
    }
}
