<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\TicketType;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class AdminBookingsComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $filter = 'all'; // all, active, completed, cancelled

    #[LiveProp(writable: true)]
    public ?string $search = null;

    public function __construct(
        private readonly BookingRepository $bookingRepository
    ) {}

    /**
     * @return list<array{
     *     booking: Booking,
     *     isCarnet: bool,
     *     totalLessons: int,
     *     completedLessons: int,
     *     remainingLessons: int,
     *     progress: float,
     *     upcomingLessons: array<int, Lesson>
     * }>
     */
    public function getAllBookings(): array
    {
        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->select('b', 'u', 'l', 'p', 's')
            ->leftJoin('b.user', 'u')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('b.payment', 'p')
            ->leftJoin('l.series', 's');

        // Apply status filter
        if ($this->filter === 'active') {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', Booking::STATUS_CONFIRMED);
        } elseif ($this->filter === 'completed') {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', Booking::STATUS_COMPLETED);
        } elseif ($this->filter === 'cancelled') {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', Booking::STATUS_CANCELLED);
        } else {
            $qb->andWhere('b.status IN (:statuses)')
                ->setParameter('statuses', [
                    Booking::STATUS_CONFIRMED,
                    Booking::STATUS_COMPLETED,
                    Booking::STATUS_CANCELLED,
                ]);
        }

        // Apply search filter
        if ($this->search) {
            $qb->andWhere('u.name LIKE :search OR u.email LIKE :search OR l.metadata.title LIKE :search')
                ->setParameter('search', '%' . $this->search . '%');
        }

        $qb->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(50);

        /** @var Booking[] $bookings */
        $bookings = $qb->getQuery()
            ->getResult();

        $result = [];
        foreach ($bookings as $booking) {
            $isCarnet = $this->isCarnetBooking($booking);

            $totalLessons = $booking->getLessons()
                ->count();
            $completedLessons = $booking->getLessons()
                ->filter(fn(Lesson $l) => $l->getMetadata() ->schedule < new \DateTimeImmutable())
                ->count();

            $upcomingLessons = $booking->getLessons()
                ->filter(fn(Lesson $l) => $l->getMetadata() ->schedule > new \DateTimeImmutable());

            $result[] = [
                'booking' => $booking,
                'isCarnet' => $isCarnet,
                'totalLessons' => $totalLessons,
                'completedLessons' => $completedLessons,
                'remainingLessons' => $totalLessons - $completedLessons,
                'progress' => $totalLessons > 0 ? (float) (($completedLessons / $totalLessons) * 100) : 0.0,
                'upcomingLessons' => array_values($upcomingLessons->toArray()),
            ];
        }

        return $result;
    }

    private function isCarnetBooking(Booking $booking): bool
    {
        foreach ($booking->getLessons() as $lesson) {
            foreach ($lesson->getTicketOptions() as $option) {
                if ($option->type === TicketType::CARNET_4) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return array{all: int, active: int, completed: int, cancelled: int}
     */
    public function getFilterCounts(): array
    {
        /** @var list<array{status: string, count: string}> $counts */
        $counts = $this->bookingRepository->createQueryBuilder('b')
            ->select('b.status', 'COUNT(b.id) as count')
            ->groupBy('b.status')
            ->getQuery()
            ->getResult();

        $result = [
            'all' => 0,
            'active' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        foreach ($counts as $count) {
            $countValue = (int) $count['count'];
            $result['all'] += $countValue;
            if ($count['status'] === Booking::STATUS_CONFIRMED) {
                $result['active'] = $countValue;
            } elseif ($count['status'] === Booking::STATUS_COMPLETED) {
                $result['completed'] = $countValue;
            } elseif ($count['status'] === Booking::STATUS_CANCELLED) {
                $result['cancelled'] = $countValue;
            }
        }

        return $result;
    }
}
