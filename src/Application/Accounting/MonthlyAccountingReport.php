<?php

declare(strict_types=1);

namespace App\Application\Accounting;

/**
 * Paid revenue of one calendar month, broken down per lesson. A payment that
 * covers several lessons is split evenly between them; payments tied to no
 * lesson (e.g. a monthly enrollment fee) are summed in $unassigned.
 */
final readonly class MonthlyAccountingReport
{
    /**
     * @param list<MonthlyAccountingLessonRow> $rows ordered by lesson date
     */
    public function __construct(
        public \DateTimeImmutable $month,
        public array $rows,
        public MonthlyAccountingSum $unassigned,
        public MonthlyAccountingSum $total,
    ) {}
}
