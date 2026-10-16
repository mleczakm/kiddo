<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Entity\Lesson;
use Brick\Money\Money;

final readonly class MonthlyAccountingLessonRow
{
    public function __construct(
        public Lesson $lesson,
        public Money $amount,
        public int $paymentsCount,
    ) {}
}
