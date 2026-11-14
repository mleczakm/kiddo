<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Cart;

final readonly class CartItemPrice
{
    public function __construct(
        public int $baseMinor,
        public int $finalMinor,
        public ?string $quoteHash = null,
        public ?\DateTimeImmutable $quotedAt = null,
    ) {}
}
