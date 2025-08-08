<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class BookingsComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository
    ) {}

    #[LiveProp(writable: true)]
    public string $activeTab = 'active';

    #[LiveProp(writable: true)]
    public string $viewMode = 'list';

    /**
     * @return list<Booking>
     */
    #[LiveAction]
    public function getCurrentBookings(): array
    {
        $user = $this->getUser();

        if (! $user) {
            return [];
        }

        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->select('b', 'l', 's')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('l.series', 's')
            ->where('b.user = :user')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('user', $user)
            ->orderBy('l.metadata.schedule', 'ASC');

        /** @var list<Booking> $result */
        $result = match ($this->activeTab) {
            'active' => $qb
                ->andWhere('l.metadata.schedule >= :now')
                ->setParameter('now', Clock::get()->now())
                ->setParameter('statuses', ['confirmed'])
                ->getQuery()
                ->getResult(),
            'past' => $qb
                ->andWhere('l.metadata.schedule <= :now')
                ->setParameter('now', Clock::get()->now())
                ->setParameter('statuses', ['confirmed', 'completed'])
                ->getQuery()
                ->getResult(),
            'cancelled' => $qb
                ->setParameter('statuses', ['cancelled', 'rescheduled', 'refunded'])
                ->getQuery()
                ->getResult(),
            default => [],
        };

        return $result;
    }

    #[LiveAction]
    public function selectTab(#[LiveArg] string $tab): void
    {
        if (in_array($tab, ['active', 'past', 'cancelled'], true)) {
            $this->activeTab = $tab;
        }
    }
}
