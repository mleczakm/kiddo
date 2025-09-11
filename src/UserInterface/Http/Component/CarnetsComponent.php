<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class CarnetsComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[LiveAction]
    public function getCarnets(): array
    {
        $user = $this->getUser();
        if (! $user) {
            return [];
        }

        /** @var list<Booking> $bookings */
        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('b', 'l', 's')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('l.series', 's')
            ->where('b.user = :user')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('statuses', [Booking::STATUS_CONFIRMED])
            ->setParameter('user', $user)
            ->orderBy('l.metadata.schedule', 'ASC')
            ->getQuery()
            ->getResult();

        $carnets = [];

        foreach ($bookings as $booking) {
            if ($booking->getLessonsMap()->lessons->count() > 1) {
                $carnets[] = $booking;
            }


        }

        return array_reverse($carnets);
    }
}
