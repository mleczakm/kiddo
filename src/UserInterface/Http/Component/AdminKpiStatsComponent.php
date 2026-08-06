<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Payment;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use App\Repository\PaymentRepository;
use Brick\Money\Money;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminKpiStatsComponent
{
    use DefaultActionTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly LessonRepository $lessonRepository,
    ) {}

    /**
     * @return array{bookingsCount: int, revenue: Money, occupancyRate: string, monthLabel: string}
     */
    public function getKpiStats(): array
    {
        $now = Clock::get()->now();
        $monthStart = $now->modify('first day of this month')
            ->setTime(0, 0, 0);
        $monthEnd = $now->modify('last day of this month')
            ->setTime(23, 59, 59);

        $bookingsCount = $this->bookingRepository->countCreatedBetween($monthStart, $monthEnd);

        $revenue = array_reduce(
            $this->paymentRepository->findPaidBetween($monthStart, $monthEnd),
            static fn(Money $carry, Payment $payment): Money => $carry->plus($payment->getAmount()),
            Money::zero('PLN'),
        );

        $lessons = $this->lessonRepository->findUpcomingInRange($monthStart, $monthEnd);
        $totalCapacity = 0;
        $totalBooked = 0;
        foreach ($lessons as $lesson) {
            $capacity = $lesson->getMetadata()->capacity;
            $totalCapacity += $capacity;
            $totalBooked += $capacity - $lesson->getAvailableSpots();
        }
        $occupancyRate = $totalCapacity > 0 ? (int) round($totalBooked / $totalCapacity * 100) : 0;

        $formatter = new \IntlDateFormatter('pl_PL', pattern: 'LLLL');

        return [
            'bookingsCount' => $bookingsCount,
            'revenue' => $revenue,
            'occupancyRate' => $occupancyRate . '%',
            'monthLabel' => ucfirst((string) $formatter->format($now)),
        ];
    }
}
