<?php

declare(strict_types=1);

namespace App\Entity;

use Brick\Money\Money;

final readonly class TicketOption
{
    public function __construct(
        public TicketType $type,
        public Money $price,
    ) {}
}
