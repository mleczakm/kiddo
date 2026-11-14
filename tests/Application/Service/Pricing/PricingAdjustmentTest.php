<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\AdjustmentType;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class PricingAdjustmentTest extends PricingEngineTestCase
{
    public function testWithNoRulesTheFinalPriceEqualsTheBasePrice(): void
    {
        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', []);

        static::assertSame(10_000, $quote->finalPriceMinor);
        static::assertSame([], $quote->adjustments);
        static::assertSame([], $quote->rejectedRuleReasons);
    }

    public function testASingleApplicableSetPriceRuleReplacesTheBasePrice(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 8_000);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);

        static::assertSame(8_000, $quote->finalPriceMinor);
        static::assertCount(1, $quote->adjustments);
        static::assertSame(-2_000, $quote->adjustments[0]->deltaMinor);
    }

    public function testTheMoreSpecificSetPriceRuleWinsOverALessSpecificOne(): void
    {
        $generic = $this->rule(AdjustmentType::SET_PRICE, 9_000, ['name' => 'Generic']);
        $specific = $this->rule(AdjustmentType::SET_PRICE, 7_000, [
            'userId' => 1,
            'ticketType' => 'one_time',
            'name' => 'Specific',
        ]);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$generic, $specific]);

        static::assertSame(7_000, $quote->finalPriceMinor);
        static::assertCount(1, $quote->adjustments);
        static::assertStringContainsString('Generic', implode(' ', $quote->rejectedRuleReasons));
    }

    public function testEqualSpecificitySetPriceRulesAreBrokenByPriority(): void
    {
        $low = $this->rule(AdjustmentType::SET_PRICE, 9_000, [
            'priority' => 1,
            'userId' => 1,
            'name' => 'Low priority',
        ]);
        $high = $this->rule(AdjustmentType::SET_PRICE, 7_000, [
            'priority' => 5,
            'userId' => 1,
            'name' => 'High priority',
        ]);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$low, $high]);

        static::assertSame(7_000, $quote->finalPriceMinor);
    }

    public function testStackableFixedAmountDiscountSubtractsFromThePrice(): void
    {
        $rule = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_500);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);

        static::assertSame(8_500, $quote->finalPriceMinor);
    }

    public function testStackablePercentageDiscountAppliesToTheRunningPrice(): void
    {
        $rule = $this->rule(AdjustmentType::PERCENTAGE_OFF, 10);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);

        static::assertSame(9_000, $quote->finalPriceMinor);
    }

    public function testMultipleStackableDiscountsCompoundInPriorityOrder(): void
    {
        // 10_000 -> fixed -1000 (priority 5, first) -> 9_000 -> 10% off (priority 1) -> 8_100
        $fixed = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000, ['priority' => 5]);
        $percent = $this->rule(AdjustmentType::PERCENTAGE_OFF, 10, ['priority' => 1]);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$percent, $fixed]);

        static::assertSame(8_100, $quote->finalPriceMinor);
        static::assertCount(2, $quote->adjustments);
    }

    public function testSetPriceRuleCombinesWithSubsequentStackableDiscounts(): void
    {
        $setPrice = $this->rule(AdjustmentType::SET_PRICE, 8_000);
        $fixed = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$setPrice, $fixed]);

        static::assertSame(7_000, $quote->finalPriceMinor);
    }

    public function testExclusivityGroupBlocksASecondRuleInTheSameGroup(): void
    {
        $first = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000, [
            'priority' => 5,
            'stackable' => false,
            'exclusivityGroup' => 'seasonal',
            'name' => 'First',
        ]);
        $second = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 2_000, [
            'priority' => 1,
            'stackable' => false,
            'exclusivityGroup' => 'seasonal',
            'name' => 'Second',
        ]);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$first, $second]);

        static::assertSame(9_000, $quote->finalPriceMinor);
        static::assertCount(1, $quote->adjustments);
        static::assertStringContainsString('Second', implode(' ', $quote->rejectedRuleReasons));
    }

    public function testDiscountsFloorAtZeroRatherThanGoingNegative(): void
    {
        $rule = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 50_000);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);

        static::assertSame(0, $quote->finalPriceMinor);
    }
}
