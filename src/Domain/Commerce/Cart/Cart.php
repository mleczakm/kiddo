<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Cart;

use Symfony\Component\Uid\Ulid;

/**
 * A logged-in customer's in-progress selection of tickets, not yet an order
 * (Stage 10 of the commerce rollout plan). Deliberately free of Doctrine
 * attributes/Messenger/controller dependencies, mapped via XML instead - see
 * Infrastructure/Doctrine/Commerce/Mapping, matching CustomerOrder's
 * convention. Unlike OrderLine (an immutable financial snapshot), a cart is
 * live and mutable: items get added/removed, prices are quotes that can
 * change, until CheckoutCart converts it. A converted cart never mutates
 * again - every write path must call assertOpen() first.
 */
final class Cart
{
    public const string STATUS_OPEN = 'open';

    public const string STATUS_CONVERTED = 'converted';

    public const string STATUS_ABANDONED = 'abandoned';

    public function __construct(
        public Ulid $id,
        public int $customerId,
        public string $currency,
        public string $status = self::STATUS_OPEN,
        public ?string $promotionCode = null,
        public ?Ulid $convertedOrderId = null,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
        public int $version = 1,
    ) {}

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Every mutation (item changes, promotion code changes, conversion)
     * must go through this first - the single place "a converted cart is
     * immutable" is enforced, rather than repeating the check in every
     * use case.
     */
    public function assertOpen(): void
    {
        if (!$this->isOpen()) {
            throw new \LogicException(sprintf('Cart %s is not open (status: %s).', $this->id, $this->status));
        }
    }

    public function convert(Ulid $orderId): void
    {
        $this->assertOpen();
        $this->status = self::STATUS_CONVERTED;
        $this->convertedOrderId = $orderId;
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
