<?php

declare(strict_types=1);

namespace App\Entity;

use Brick\Money\Money;

final class TicketOption
{
    public function __construct(
        public readonly TicketType $type,
        public readonly Money $price,
    ) {}
}
