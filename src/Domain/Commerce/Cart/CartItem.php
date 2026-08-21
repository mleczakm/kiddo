<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Cart;

use Symfony\Component\Uid\Ulid;

/**
 * One selected ticket/participant within a Cart (Stage 10 of the commerce
 * rollout plan) - mirrors OrderLine's shape but stays mutable, since a cart
 * item's price is only ever a quote until checkout converts it into a real
 * OrderLine snapshot. $lessonId is always the specific occurrence the
 * customer picked, even for a CARNET_4 ticket; the series it belongs to is
 * derived at quote/checkout time (see OrderPlacementService), not stored
 * here.
 */
final class CartItem
{
    public function __construct(
        public readonly Ulid $id,
        public readonly Ulid $cartId,
        public readonly Ulid $lessonId,
        public readonly string $ticketType,
        public ?Ulid $participantId,
        public int $basePriceMinor,
        public int $finalPriceMinor,
        public readonly string $currency,
        public ?string $pricingQuoteHash,
        public ?\DateTimeImmutable $quotedAt,
        public readonly \DateTimeImmutable $addedAt = new \DateTimeImmutable(),
    ) {}

    /**
     * Whether $other selects the same ticket/participant combination as this
     * item - AddCartItem's explicit duplicate rule (see DuplicateCartItemException):
     * a cart can only hold one item per lesson/ticket-type/participant.
     */
    public function matchesSelection(Ulid $lessonId, string $ticketType, ?Ulid $participantId): bool
    {
        return (
            $this->lessonId->equals($lessonId)
            && $this->ticketType === $ticketType
            && ($this->participantId === null) === ($participantId === null)
            && ($this->participantId === null || $this->participantId->equals($participantId))
        );
    }
}
