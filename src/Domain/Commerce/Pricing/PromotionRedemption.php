<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

use Symfony\Component\Uid\Ulid;

final class PromotionRedemption
{
    public const string STATUS_RESERVED = 'reserved';
    public const string STATUS_CONSUMED = 'consumed';
    public const string STATUS_RELEASED = 'released';

    public function __construct(
        public readonly Ulid $id,
        public readonly Ulid $pricingRuleId,
        public readonly Ulid $orderId,
        public readonly Ulid $orderLineId,
        public readonly int $customerId,
        public string $status = self::STATUS_RESERVED,
        public readonly \DateTimeImmutable $reservedAt = new \DateTimeImmutable(),
        public ?\DateTimeImmutable $consumedAt = null,
        public ?\DateTimeImmutable $releasedAt = null,
    ) {}

    /**
     * Marking-store hook for the `promotion_redemption` state machine - do not call
     * directly, apply `consume` / `release` through StateMachinesInterface instead.
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
        match ($status) {
            self::STATUS_CONSUMED => $this->consumedAt = new \DateTimeImmutable(),
            self::STATUS_RELEASED => $this->releasedAt = new \DateTimeImmutable(),
            default => null,
        };
    }
}
