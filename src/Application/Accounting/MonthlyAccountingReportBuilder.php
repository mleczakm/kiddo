<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Application\Repository\PaymentRepositoryInterface;
use App\Entity\Booking;
use Brick\Money\Money;

final readonly class MonthlyAccountingReportBuilder
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
    ) {}

    /**
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Money\Exception\MoneyMismatchException
     * @throws \InvalidArgumentException
     */
    public function build(\DateTimeImmutable $anyDayOfMonth): MonthlyAccountingReport
    {
        $month = $anyDayOfMonth->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd = $month->modify('last day of this month')->setTime(23, 59, 59);

        $perLesson = [];
        $unassigned = new MonthlyAccountingSum(Money::zero('PLN'), 0);
        $total = new MonthlyAccountingSum(Money::zero('PLN'), 0);

        foreach ($this->payments->findPaidBetween($month, $monthEnd) as $payment) {
            $total = new MonthlyAccountingSum($total->amount->plus($payment->getAmount()), $total->paymentsCount + 1);

            $lessons = [];
            foreach ($payment->getBookings() as $booking) {
                /** @var Booking $booking */
                foreach ($booking->getLessons() as $lesson) {
                    $lessons[(string) $lesson->getId()] = $lesson;
                }
            }

            if ($lessons === []) {
                $unassigned = new MonthlyAccountingSum(
                    $unassigned->amount->plus($payment->getAmount()),
                    $unassigned->paymentsCount + 1,
                );
                continue;
            }

            // Money::split() hands out the rounding remainder, so the shares
            // always add up to the payment amount exactly.
            $shares = $payment->getAmount()->split(count($lessons));
            foreach (array_values($lessons) as $index => $lesson) {
                $key = (string) $lesson->getId();
                $existing = $perLesson[$key] ?? null;
                $perLesson[$key] = $existing === null
                    ? new MonthlyAccountingLessonRow($lesson, $shares[$index], 1)
                    : new MonthlyAccountingLessonRow(
                        $lesson,
                        $existing->amount->plus($shares[$index]),
                        $existing->paymentsCount + 1,
                    );
            }
        }

        $rows = array_values($perLesson);
        usort(
            $rows,
            static fn(MonthlyAccountingLessonRow $a, MonthlyAccountingLessonRow $b): int => (
                $a->lesson->schedule <=> $b->lesson->schedule
            ),
        );

        return new MonthlyAccountingReport($month, $rows, $unassigned, $total);
    }
}
