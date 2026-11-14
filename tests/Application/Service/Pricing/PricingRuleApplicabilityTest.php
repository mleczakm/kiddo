<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Application\Service\Pricing\PricingRuleUsage;
use App\Domain\Commerce\Pricing\AdjustmentType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Ulid;

#[Group('unit')]
final class PricingRuleApplicabilityTest extends PricingEngineTestCase
{
    public function testPromotionCodeRuleOnlyAppliesWhenTheContextSuppliesAMatchingCode(): void
    {
        $rule = $this->rule(AdjustmentType::PERCENTAGE_OFF, 20, ['promotionCode' => 'WELCOME10']);

        $withoutCode = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);
        $withCode = $this->engine->quote($this->context(promotionCode: 'welcome10'), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $withoutCode->finalPriceMinor);
        static::assertSame(8_000, $withCode->finalPriceMinor);
    }

    public function testPromotionCodeRuleIsBlockedByAnAlreadyConsumedExclusivityGroup(): void
    {
        $seasonal = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000, [
            'stackable' => false,
            'exclusivityGroup' => 'discounts',
            'name' => 'Seasonal',
        ]);
        $promo = $this->rule(AdjustmentType::PERCENTAGE_OFF, 50, [
            'stackable' => false,
            'exclusivityGroup' => 'discounts',
            'promotionCode' => 'BIGDEAL',
            'name' => 'Promo',
        ]);

        $quote = $this->engine->quote($this->context(promotionCode: 'BIGDEAL'), 10_000, 'PLN', [$seasonal, $promo]);

        static::assertSame(9_000, $quote->finalPriceMinor);
        static::assertStringContainsString('Promo', implode(' ', $quote->rejectedRuleReasons));
    }

    public function testARuleScopedToADifferentLessonDoesNotApply(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['lessonId' => new Ulid()]);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
        static::assertNotEmpty($quote->rejectedRuleReasons);
    }

    public function testARuleScopedToASeriesDoesNotApplyWhenTheContextHasNoSeries(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['seriesId' => new Ulid()]);

        $quote = $this->engine->quote($this->context(seriesId: null), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testARuleScopedToADifferentTicketTypeDoesNotApply(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['ticketType' => 'carnet_4']);

        $quote = $this->engine->quote($this->context(ticketType: 'one_time'), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testARuleScopedToADifferentUserDoesNotApply(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['userId' => 42]);

        $quote = $this->engine->quote($this->context(userId: 1), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testValidityWindowBoundariesAreInclusive(): void
    {
        $from = new \DateTimeImmutable('2026-06-01 00:00:00');
        $until = new \DateTimeImmutable('2026-06-30 23:59:59');
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['validFrom' => $from, 'validUntil' => $until]);

        $atStart = $this->engine->quote($this->context(at: $from), 10_000, 'PLN', [$rule]);
        $atEnd = $this->engine->quote($this->context(at: $until), 10_000, 'PLN', [$rule]);

        static::assertSame(5_000, $atStart->finalPriceMinor);
        static::assertSame(5_000, $atEnd->finalPriceMinor);
    }

    public function testValidityWindowExcludesTimesOutsideTheBoundary(): void
    {
        $from = new \DateTimeImmutable('2026-06-01 00:00:00');
        $until = new \DateTimeImmutable('2026-06-30 23:59:59');
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['validFrom' => $from, 'validUntil' => $until]);

        $before = $this->engine->quote($this->context(at: $from->modify('-1 second')), 10_000, 'PLN', [$rule]);
        $after = $this->engine->quote($this->context(at: $until->modify('+1 second')), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $before->finalPriceMinor);
        static::assertSame(10_000, $after->finalPriceMinor);
    }

    public function testAUsageLimitAlreadyReachedPreventsTheRuleFromApplying(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['usageLimit' => 3]);
        $id = (string) $rule->id;

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule], new PricingRuleUsage(totalByRule: [
            $id => 3,
        ]));

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testAPerUserLimitAlreadyReachedPreventsTheRuleFromApplying(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['perUserLimit' => 1]);
        $id = (string) $rule->id;

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule], new PricingRuleUsage(customerByRule: [
            $id => 1,
        ]));

        static::assertSame(10_000, $quote->finalPriceMinor);
    }
}
