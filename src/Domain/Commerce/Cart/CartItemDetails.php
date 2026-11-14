<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Cart;

use Symfony\Component\Uid\Ulid;

final readonly class CartItemDetails
{
    public function __construct(
        public ?Ulid $participantId,
        public string $currency,
        public CartItemPrice $price,
        public \DateTimeImmutable $addedAt = new \DateTimeImmutable(),
    ) {}
}
