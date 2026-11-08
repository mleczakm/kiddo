<?php

declare(strict_types=1);

namespace App\Tests\Application\Workflow;

use App\Application\Workflow\StateMachinesInterface;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Domain\Commerce\Pricing\PromotionRedemption;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Workflow\Exception\LogicException;

#[Group('functional')]
final class CommerceStateMachinesTest extends KernelTestCase
{
    private StateMachinesInterface $stateMachines;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $stateMachines = self::getContainer()->get(StateMachinesInterface::class);
        \assert($stateMachines instanceof StateMachinesInterface);
        $this->stateMachines = $stateMachines;
    }

    public function testOrderPayIsAcceptedLateButExpireNeverOverridesPaid(): void
    {
        $expired = $this->order(CustomerOrder::STATUS_PLACED);
        $this->stateMachines->apply($expired, 'expire');
        static::assertSame(CustomerOrder::STATUS_EXPIRED, $expired->getStatus());
        $this->stateMachines->apply($expired, 'pay');
        static::assertSame(CustomerOrder::STATUS_PAID, $expired->getStatus());

        static::assertFalse($this->stateMachines->can($expired, 'expire'));
        static::assertFalse($this->stateMachines->can($expired, 'pay'));
    }

    public function testConvertedCartCannotBeConvertedAgain(): void
    {
        $cart = new Cart(id: new Ulid(), customerId: 1, currency: 'PLN');
        $orderId = new Ulid();

        $this->stateMachines->apply($cart, 'convert');
        $cart->recordConversion($orderId);

        static::assertFalse($cart->isOpen());
        static::assertTrue($cart->convertedOrderId?->equals($orderId));
        $this->expectException(LogicException::class);
        $this->stateMachines->apply($cart, 'convert');
    }

    public function testPromotionRedemptionOutcomesAreFinal(): void
    {
        $redemption = new PromotionRedemption(new Ulid(), new Ulid(), new Ulid(), new Ulid(), 1);

        $this->stateMachines->apply($redemption, 'consume');
        static::assertSame(PromotionRedemption::STATUS_CONSUMED, $redemption->status);
        static::assertInstanceOf(\DateTimeImmutable::class, $redemption->consumedAt);
        static::assertFalse($this->stateMachines->applyIfPossible($redemption, 'release'));
        static::assertNull($redemption->releasedAt);
    }

    public function testPricingRuleCanBeDisabledAndEnabled(): void
    {
        $rule = new PricingRule(new Ulid(), 'rule', AdjustmentType::FIXED_AMOUNT_OFF, 500);

        static::assertTrue($rule->isActive());
        static::assertFalse($this->stateMachines->applyIfPossible($rule, 'enable'));
        $this->stateMachines->apply($rule, 'disable');
        static::assertFalse($rule->isActive());
        $this->stateMachines->apply($rule, 'enable');
        static::assertTrue($rule->isActive());
    }

    private function order(string $status): CustomerOrder
    {
        return new CustomerOrder(
            id: new Ulid(),
            orderNumber: 'ORD-1',
            customerId: 1,
            status: $status,
            currency: 'PLN',
            subtotalMinor: 100,
            discountTotalMinor: 0,
            totalMinor: 100,
            placedAt: new \DateTimeImmutable(),
            expiresAt: null,
            checkoutKey: 'key',
            source: CustomerOrder::SOURCE_CART,
        );
    }
}
