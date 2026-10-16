<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use Brick\Money\Money;

final readonly class MonthlyAccountingSum
{
    public function __construct(
        public Money $amount,
        public int $paymentsCount,
    ) {}
}
