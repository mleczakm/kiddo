<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Infrastructure\Doctrine\Repository\PaymentRepository;
use Brick\Money\Money;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
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

    #[LiveAction]
    public function previousMonth(): void
    {
        $this->month = $this->displayedMonth()->modify('-1 month')->format('Y-m');
    }

    #[LiveAction]
    public function nextMonth(): void
    {
        $this->month = $this->displayedMonth()->modify('+1 month')->format('Y-m');
    }

    #[LiveAction]
    public function currentMonth(): void
    {
        $this->month = '';
    }

    public function getMonthKey(): string
    {
        return $this->displayedMonth()->format('Y-m');
    }

    public function isCurrentMonth(): bool
    {
        return $this->getMonthKey() === Clock::get()->now()->format('Y-m');
    }

    private function displayedMonth(): \DateTimeImmutable
    {
        $now = Clock::get()->now();
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) !== 1) {
            return $now->modify('first day of this month')->setTime(0, 0, 0);
        }

        return new \DateTimeImmutable($this->month . '-01 00:00:00', $now->getTimezone());
    }

    /**
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Money\Exception\MoneyMismatchException
     *
     * @return array{bookingsCount: int, revenue: Money, occupancyRate: string, monthLabel: string}
     */
    public function getKpiStats(): array
    {
        $monthStart = $this->displayedMonth();
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

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

        $formatter = new \IntlDateFormatter('pl_PL', pattern: 'LLLL');

        return [
            'bookingsCount' => $bookingsCount,
            'revenue' => $revenue,
            'occupancyRate' => $occupancyRate . '%',
            'monthLabel' => ucfirst((string) $formatter->format($monthStart)),
        ];
    }
}
