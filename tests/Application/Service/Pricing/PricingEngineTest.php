<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Application\Service\Pricing\PricingEngine;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PriceQuote;
use App\Domain\Commerce\Pricing\PricingContext;
use App\Domain\Commerce\Pricing\PricingRule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

#[Group('unit')]
final class PricingEngineTest extends TestCase
{
    private PricingEngine $engine;

    private Ulid $lessonId;

    #[\Override]
    protected function setUp(): void
    {
        $this->engine = new PricingEngine();
        $this->lessonId = new Ulid();
    }

    private function context(
        ?int $userId = 1,
        ?Ulid $seriesId = null,
        string $ticketType = 'one_time',
        ?string $promotionCode = null,
        ?\DateTimeImmutable $at = null,
    ): PricingContext {
        return new PricingContext(
            userId: $userId,
            lessonId: $this->lessonId,
            seriesId: $seriesId,
            ticketType: $ticketType,
            evaluationTime: $at ?? new \DateTimeImmutable('2026-06-15 12:00:00'),
            promotionCode: $promotionCode,
        );
    }

    private function rule(
        AdjustmentType $type,
        int $value,
        int $priority = 0,
        bool $stackable = true,
        ?string $exclusivityGroup = null,
        ?int $userId = null,
        ?Ulid $lessonId = null,
        ?Ulid $seriesId = null,
        ?string $ticketType = null,
        ?string $promotionCode = null,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validUntil = null,
        ?int $usageLimit = null,
        ?int $perUserLimit = null,
        string $name = 'Test rule',
    ): PricingRule {
        return new PricingRule(
            id: new Ulid(),
            name: $name,
            adjustmentType: $type,
            adjustmentValue: $value,
            priority: $priority,
            stackable: $stackable,
            exclusivityGroup: $exclusivityGroup,
            userId: $userId,
            seriesId: $seriesId,
            lessonId: $lessonId,
            ticketType: $ticketType,
            promotionCode: $promotionCode,
            validFrom: $validFrom,
            validUntil: $validUntil,
            usageLimit: $usageLimit,
            perUserLimit: $perUserLimit,
        );
    }

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
        $generic = $this->rule(AdjustmentType::SET_PRICE, 9_000, name: 'Generic');
        $specific = $this->rule(AdjustmentType::SET_PRICE, 7_000, userId: 1, ticketType: 'one_time', name: 'Specific');

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$generic, $specific]);

        static::assertSame(7_000, $quote->finalPriceMinor);
        static::assertCount(1, $quote->adjustments);
        static::assertStringContainsString('Generic', implode(' ', $quote->rejectedRuleReasons));
    }

    public function testEqualSpecificitySetPriceRulesAreBrokenByPriority(): void
    {
        $low = $this->rule(AdjustmentType::SET_PRICE, 9_000, priority: 1, userId: 1, name: 'Low priority');
        $high = $this->rule(AdjustmentType::SET_PRICE, 7_000, priority: 5, userId: 1, name: 'High priority');

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
        $fixed = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000, priority: 5);
        $percent = $this->rule(AdjustmentType::PERCENTAGE_OFF, 10, priority: 1);

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
        $first = $this->rule(
            AdjustmentType::FIXED_AMOUNT_OFF,
            1_000,
            priority: 5,
            stackable: false,
            exclusivityGroup: 'seasonal',
            name: 'First',
        );
        $second = $this->rule(
            AdjustmentType::FIXED_AMOUNT_OFF,
            2_000,
            priority: 1,
            stackable: false,
            exclusivityGroup: 'seasonal',
            name: 'Second',
        );

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$first, $second]);

        static::assertSame(9_000, $quote->finalPriceMinor);
        static::assertCount(1, $quote->adjustments);
        static::assertStringContainsString('Second', implode(' ', $quote->rejectedRuleReasons));
    }

    public function testPromotionCodeRuleOnlyAppliesWhenTheContextSuppliesAMatchingCode(): void
    {
        $rule = $this->rule(AdjustmentType::PERCENTAGE_OFF, 20, promotionCode: 'WELCOME10');

        $withoutCode = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);
        $withCode = $this->engine->quote($this->context(promotionCode: 'welcome10'), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $withoutCode->finalPriceMinor);
        static::assertSame(8_000, $withCode->finalPriceMinor);
    }

    public function testPromotionCodeRuleIsBlockedByAnAlreadyConsumedExclusivityGroup(): void
    {
        $seasonal = $this->rule(
            AdjustmentType::FIXED_AMOUNT_OFF,
            1_000,
            stackable: false,
            exclusivityGroup: 'discounts',
            name: 'Seasonal',
        );
        $promo = $this->rule(
            AdjustmentType::PERCENTAGE_OFF,
            50,
            stackable: false,
            exclusivityGroup: 'discounts',
            promotionCode: 'BIGDEAL',
            name: 'Promo',
        );

        $quote = $this->engine->quote($this->context(promotionCode: 'BIGDEAL'), 10_000, 'PLN', [$seasonal, $promo]);

        static::assertSame(9_000, $quote->finalPriceMinor);
        static::assertStringContainsString('Promo', implode(' ', $quote->rejectedRuleReasons));
    }

    public function testDiscountsFloorAtZeroRatherThanGoingNegative(): void
    {
        $rule = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 50_000);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);

        static::assertSame(0, $quote->finalPriceMinor);
    }

    public function testARuleScopedToADifferentLessonDoesNotApply(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, lessonId: new Ulid());

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
        static::assertNotEmpty($quote->rejectedRuleReasons);
    }

    public function testARuleScopedToASeriesDoesNotApplyWhenTheContextHasNoSeries(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, seriesId: new Ulid());

        $quote = $this->engine->quote($this->context(seriesId: null), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testARuleScopedToADifferentTicketTypeDoesNotApply(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, ticketType: 'carnet_4');

        $quote = $this->engine->quote($this->context(ticketType: 'one_time'), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testARuleScopedToADifferentUserDoesNotApply(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, userId: 42);

        $quote = $this->engine->quote($this->context(userId: 1), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testValidityWindowBoundariesAreInclusive(): void
    {
        $from = new \DateTimeImmutable('2026-06-01 00:00:00');
        $until = new \DateTimeImmutable('2026-06-30 23:59:59');
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, validFrom: $from, validUntil: $until);

        $atStart = $this->engine->quote($this->context(at: $from), 10_000, 'PLN', [$rule]);
        $atEnd = $this->engine->quote($this->context(at: $until), 10_000, 'PLN', [$rule]);

        static::assertSame(5_000, $atStart->finalPriceMinor);
        static::assertSame(5_000, $atEnd->finalPriceMinor);
    }

    public function testValidityWindowExcludesTimesOutsideTheBoundary(): void
    {
        $from = new \DateTimeImmutable('2026-06-01 00:00:00');
        $until = new \DateTimeImmutable('2026-06-30 23:59:59');
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, validFrom: $from, validUntil: $until);

        $before = $this->engine->quote($this->context(at: $from->modify('-1 second')), 10_000, 'PLN', [$rule]);
        $after = $this->engine->quote($this->context(at: $until->modify('+1 second')), 10_000, 'PLN', [$rule]);

        static::assertSame(10_000, $before->finalPriceMinor);
        static::assertSame(10_000, $after->finalPriceMinor);
    }

    public function testAUsageLimitAlreadyReachedPreventsTheRuleFromApplying(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, usageLimit: 3);
        $id = (string) $rule->id;

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule], usageCountsByRuleId: [$id => 3]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testAPerUserLimitAlreadyReachedPreventsTheRuleFromApplying(): void
    {
        $rule = $this->rule(AdjustmentType::SET_PRICE, 5_000, perUserLimit: 1);
        $id = (string) $rule->id;

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$rule], userUsageCountsByRuleId: [$id => 1]);

        static::assertSame(10_000, $quote->finalPriceMinor);
    }

    public function testTheSameInputsProduceTheSameQuoteHash(): void
    {
        $rule = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000);
        $at = new \DateTimeImmutable('2026-06-15 12:00:00');

        $first = $this->engine->quote($this->context(at: $at), 10_000, 'PLN', [$rule]);
        $second = $this->engine->quote($this->context(at: $at), 10_000, 'PLN', [$rule]);

        static::assertSame($first->quoteHash, $second->quoteHash);
    }

    public function testADifferentPromotionCodeProducesADifferentQuoteHash(): void
    {
        $at = new \DateTimeImmutable('2026-06-15 12:00:00');

        $withoutCode = $this->engine->quote($this->context(at: $at), 10_000, 'PLN', []);
        $withCode = $this->engine->quote($this->context(at: $at, promotionCode: 'X'), 10_000, 'PLN', []);

        static::assertNotSame($withoutCode->quoteHash, $withCode->quoteHash);
    }

    public function testEveryCandidateRuleThatCannotApplyIsRecordedAsRejected(): void
    {
        $wrongUser = $this->rule(AdjustmentType::SET_PRICE, 5_000, userId: 999, name: 'Wrong user');
        $expired = $this->rule(
            AdjustmentType::FIXED_AMOUNT_OFF,
            500,
            validUntil: new \DateTimeImmutable('2020-01-01'),
            name: 'Expired',
        );

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$wrongUser, $expired]);

        static::assertInstanceOf(PriceQuote::class, $quote);
        static::assertCount(2, $quote->rejectedRuleReasons);
        static::assertStringContainsString('Wrong user', implode(' ', $quote->rejectedRuleReasons));
        static::assertStringContainsString('Expired', implode(' ', $quote->rejectedRuleReasons));
    }
}
