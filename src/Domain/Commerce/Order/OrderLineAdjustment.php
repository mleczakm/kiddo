<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Order;

use Symfony\Component\Uid\Ulid;

/**
 * A single discount/price adjustment applied to an OrderLine, recorded so
 * the line's final price is auditable after the fact. The adjustment rule
 * types themselves (fixed amount, percentage, set price) belong to the
 * pricing engine (see Stage 7 of the commerce rollout plan) - this table
 * only records what was applied, not the rule that produced it.
 */
final class OrderLineAdjustment
{
    public const TYPE_SET_PRICE = 'set_price';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const TYPE_PERCENTAGE = 'percentage';

    public function __construct(
        private readonly Ulid $id,
        private readonly Ulid $orderLineId,
        private readonly string $type,
        private readonly int $amountMinor,
        private readonly ?string $label,
    ) {}

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getOrderLineId(): Ulid
    {
        return $this->orderLineId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }
}
