<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

#[Group('unit')]
final class PricingRuleStatusTest extends TestCase
{
    private function rule(): PricingRule
    {
        return new PricingRule(
            id: new Ulid(),
            name: 'Test rule',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 500,
        );
    }

    public function testIsActiveByDefault(): void
    {
        $rule = $this->rule();

        static::assertTrue($rule->isActive());
        static::assertSame(PricingRule::STATUS_ACTIVE, $rule->status);
    }

    public function testDisableSetsStatusToDisabled(): void
    {
        $rule = $this->rule();

        $rule->disable();

        static::assertFalse($rule->isActive());
        static::assertSame(PricingRule::STATUS_DISABLED, $rule->status);
    }

    public function testFieldsAreDirectlyMutableForAdminEditing(): void
    {
        $rule = $this->rule();

        $rule->name = 'Renamed';
        $rule->priority = 10;
        $rule->adjustmentValue = 999;

        static::assertSame('Renamed', $rule->name);
        static::assertSame(10, $rule->priority);
        static::assertSame(999, $rule->adjustmentValue);
    }

    public function testDefaultVersionIsOne(): void
    {
        static::assertSame(1, $this->rule()->version);
    }
}
