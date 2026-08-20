<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Order;

use Symfony\Component\Uid\Ulid;

/**
 * The commerce order aggregate root. Deliberately free of Doctrine
 * attributes, Messenger, Workflow, and controller dependencies - mapped via
 * XML (see Infrastructure/Doctrine/Commerce/Mapping) instead, so this class
 * stays a plain domain object. Nothing constructs or reads this table yet
 * (see Stage 4 of the commerce rollout plan): this is additive schema only.
 */
final class CustomerOrder
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PLACED = 'placed';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const SOURCE_FAST_TRACK = 'fast_track';

    public const SOURCE_CART = 'cart';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_CHAT = 'chat';

    public const SOURCE_LEGACY = 'legacy';

    public function __construct(
        private readonly Ulid $id,
        private readonly string $orderNumber,
        private readonly int $customerId,
        private string $status,
        private readonly string $currency,
        private int $subtotalMinor,
        private int $discountTotalMinor,
        private int $totalMinor,
        private readonly \DateTimeImmutable $placedAt,
        private ?\DateTimeImmutable $expiresAt,
        private readonly string $checkoutKey,
        private readonly string $source,
        private int $version = 1,
    ) {}

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSubtotalMinor(): int
    {
        return $this->subtotalMinor;
    }

    public function getDiscountTotalMinor(): int
    {
        return $this->discountTotalMinor;
    }

    public function getTotalMinor(): int
    {
        return $this->totalMinor;
    }

    public function getPlacedAt(): \DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCheckoutKey(): string
    {
        return $this->checkoutKey;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
