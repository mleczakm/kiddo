<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\TicketType;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardAction extends AbstractController
{
    #[Route(path: [
        'en' => '/dashboard',
        'pl' => '/panel',
    ], name: 'dashboard')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager,
        #[CurrentUser]
        User $user,
    ): Response {
        /** @var list<Booking> $bookings */
        $bookings = $bookingRepository->createQueryBuilder('b')
            ->select('b', 'l', 's')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('l.series', 's')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.metadata.schedule', 'ASC')
            ->getQuery()
            ->getResult();

        // Separate active and cancelled bookings and identify carnets
        $activeBookings = [];
        $cancelledBookings = [];
        $carnets = [];

        foreach ($bookings as $booking) {
            $isCarnet = false;

            // Check if any lesson in this booking has a CARNET_4 ticket option
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

                // For carnets, we want to group by the series
                $seriesId = $firstLesson->getSeries()?->getId()
                    ->toRfc4122() ?? 'unknown';
                if (! isset($carnets[$seriesId])) {
                    $carnets[$seriesId] = [
                        'title' => $firstLesson
                            ->getMetadata()
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

                // Add carnet lessons to active bookings if not cancelled
                if ($booking->getStatus() !== Booking::STATUS_CANCELLED) {
                    $activeBookings[] = $booking;
                }
            }

            // Handle non-carnet bookings
            if (! $isCarnet) {
                if ($booking->getStatus() === Booking::STATUS_CANCELLED) {
                    $cancelledBookings[] = $booking;
                } else {
                    $activeBookings[] = $booking;
                }
            }
        }

        // Count all active lessons across all bookings
        $activeLessonsCount = array_reduce(
            $activeBookings,
            fn($carry, $booking) => $carry + $booking->getLessons()
                ->count(),
            0
        );

        return $this->render(
            'dashboard.html.twig',
            [
                'activeBookings' => $activeBookings,
                'cancelledBookings' => $cancelledBookings,
                'carnets' => $carnets,
                'activeLessonsCount' => $activeLessonsCount,
                'page' => [
                    'title' => 'dashboard.title',
                    'description' => 'dashboard.description',
                ],
            ]
        );
    }
}
