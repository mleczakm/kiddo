<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Accounting\ReportingMonth;
use App\Application\Repository\PaymentRepositoryInterface;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

class PlatformFeeCalculator
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
    ) {}

    /**
     * The 2% commission a single payment adds to the platform fee.
     *
     * @throws \Brick\Math\Exception\MathException
     */
    public function commissionOn(Money $paymentAmount): Money
    {
        return $paymentAmount->multipliedBy('0.02', RoundingMode::HALF_UP);
    }

    /**
     * The fee a past (or current) month adds up to, derived from the payments
     * paid in it. The stored running balance has no per-month history, so this
     * recomputes the same 2% per paid payment that the pay transition adds to
     * it; it does not reflect settlements made against the balance.
     *
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Money\Exception\MoneyMismatchException
     * @throws \InvalidArgumentException
     */
    public function getFeeForMonth(\DateTimeImmutable $monthStart): Money
    {
        $fee = Money::zero('PLN');
        foreach ($this->paymentRepository->findPaidBetween($monthStart, ReportingMonth::end($monthStart)) as $payment) {
            $fee = $fee->plus($this->commissionOn($payment->getAmount()));
        }

        return $fee;
    }
}
