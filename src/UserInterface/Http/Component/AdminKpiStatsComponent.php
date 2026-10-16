<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Accounting\ReportingMonth;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Infrastructure\Doctrine\Repository\PaymentRepository;
use Brick\Money\Money;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminKpiStatsComponent
{
    use DefaultActionTrait;

    /** Displayed month as `Y-m`; empty means the current month. */
    #[LiveProp(writable: false)]
    public string $month = '';

    public function __construct(
        private BookingRepository $bookingRepository,
        private PaymentRepository $paymentRepository,
        private LessonRepository $lessonRepository,
    ) {}

    /**
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Money\Exception\MoneyMismatchException
     *
     * @return array{bookingsCount: int, revenue: Money, occupancyRate: string, monthLabel: string}
     */
    public function getKpiStats(): array
    {
        $monthStart = ReportingMonth::start($this->month, Clock::get()->now());
        $monthEnd = ReportingMonth::end($monthStart);

        $bookingsCount = $this->bookingRepository->countCreatedBetween($monthStart, $monthEnd);

        $revenue = Money::zero('PLN');
        foreach ($this->paymentRepository->findPaidBetween($monthStart, $monthEnd) as $payment) {
            $revenue = $revenue->plus($payment->getAmount());
        }

        $lessons = $this->lessonRepository->findUpcomingInRange($monthStart, $monthEnd);
        $totalCapacity = 0;
        $totalBooked = 0;
        foreach ($lessons as $lesson) {
            $capacity = $lesson->getMetadata()->capacity;
            $totalCapacity += $capacity;
            $totalBooked += $capacity - $lesson->getAvailableSpots();
        }
        $occupancyRate = $totalCapacity > 0 ? (int) round(($totalBooked / $totalCapacity) * 100) : 0;

        return [
            'bookingsCount' => $bookingsCount,
            'revenue' => $revenue,
            'occupancyRate' => $occupancyRate . '%',
            'monthLabel' => ReportingMonth::label($monthStart),
        ];
    }
}
