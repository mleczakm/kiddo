<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PriceQuote;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class PricingQuoteHashTest extends PricingEngineTestCase
{
    public function testTheSameInputsProduceTheSameQuoteHash(): void
    {
        $rule = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000);
        $at = new \DateTimeImmutable('2026-06-15 12:00:00');

        $first = $this->engine->quote($this->context(at: $at), 10_000, 'PLN', [$rule]);
        $second = $this->engine->quote($this->context(at: $at), 10_000, 'PLN', [$rule]);

        static::assertSame($first->quoteHash, $second->quoteHash);
    }

    public function testEvaluationTimeAloneDoesNotChangeTheQuoteHash(): void
    {
        $first = $this->engine->quote(
            $this->context(at: new \DateTimeImmutable('2026-06-15 12:00:00')),
            10_000,
            'PLN',
            [],
        );
        $second = $this->engine->quote(
            $this->context(at: new \DateTimeImmutable('2026-06-15 12:00:01')),
            10_000,
            'PLN',
            [],
        );

        static::assertSame($first->quoteHash, $second->quoteHash);
    }

    public function testCrossingATimeRuleBoundaryChangesTheQuoteHash(): void
    {
        $rule = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 1_000, [
            'validFrom' => new \DateTimeImmutable('2026-06-15 13:00:00'),
        ]);
        $before = $this->engine->quote(
            $this->context(at: new \DateTimeImmutable('2026-06-15 12:59:59')),
            10_000,
            'PLN',
            [$rule],
        );
        $after = $this->engine->quote(
            $this->context(at: new \DateTimeImmutable('2026-06-15 13:00:00')),
            10_000,
            'PLN',
            [$rule],
        );

        static::assertNotSame($before->quoteHash, $after->quoteHash);
        static::assertSame(10_000, $before->finalPriceMinor);
        static::assertSame(9_000, $after->finalPriceMinor);
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
        $wrongUser = $this->rule(AdjustmentType::SET_PRICE, 5_000, ['userId' => 999, 'name' => 'Wrong user']);
        $expired = $this->rule(AdjustmentType::FIXED_AMOUNT_OFF, 500, [
            'validUntil' => new \DateTimeImmutable('2020-01-01'),
            'name' => 'Expired',
        ]);

        $quote = $this->engine->quote($this->context(), 10_000, 'PLN', [$wrongUser, $expired]);

        static::assertInstanceOf(PriceQuote::class, $quote);
        static::assertCount(2, $quote->rejectedRuleReasons);
        static::assertStringContainsString('Wrong user', implode(' ', $quote->rejectedRuleReasons));
        static::assertStringContainsString('Expired', implode(' ', $quote->rejectedRuleReasons));
    }
}
