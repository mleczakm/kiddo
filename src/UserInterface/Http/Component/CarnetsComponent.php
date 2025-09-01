<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\TicketType;
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
            $isCarnet = false;

            foreach ($booking->getLessons() as $lesson) {
                foreach ($lesson->getTicketOptions() as $option) {
                    if ($option->type === TicketType::CARNET_4) {
                        $isCarnet = true;
                        break 2;
                    }
                }
            }

            if ($isCarnet) {
                /** @var Lesson $firstLesson */
                $firstLesson = $booking->getLessons()
                    ->first();

                $seriesId = $firstLesson->getSeries()?->getId()
                    ->toRfc4122() ?? 'unknown';
                if (! isset($carnets[$seriesId])) {
                    $carnets[$seriesId] = [
                        'title' => $firstLesson->getMetadata()
                            ->title,
                        'bookings' => [],
                        'totalLessons' => 0,
                        'usedLessons' => 0,
                    ];
                }
                $carnets[$seriesId]['bookings'][] = $booking;
                $carnets[$seriesId]['totalLessons'] += $booking->getLessons()->count();
                $carnets[$seriesId]['usedLessons'] += $booking->getLessons()->filter(
                    fn(Lesson $l) => $l->getMetadata()
                        ->schedule < new \DateTimeImmutable()
                )->count();

                $carnets[$seriesId]['status'] = $carnets[$seriesId]['totalLessons'] - $carnets[$seriesId]['usedLessons'] > 0
                    ? 'active'
                    : 'past';
            }
        }

        // Filter out expired carnets - only return active ones
        $activeCarnets = array_filter($carnets, fn($carnet) => $carnet['status'] === 'active');

        return array_reverse($activeCarnets);
    }
}
