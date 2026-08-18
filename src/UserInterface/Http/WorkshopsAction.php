<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorkshopsAction extends AbstractController
{
    #[Route(path: [
        'pl' => 'warsztaty',
        'en' => 'workshops',
    ], name: 'workshops')]
    public function __invoke(Request $request): Response
    {
        $weekParam = $request->query->get('week');
        $now = Clock::get()->now();

        if ($weekParam) {
            try {
                $referenceDate = new \DateTimeImmutable($weekParam);
            } catch (\Exception) {
                $referenceDate = $now;
            }
        } else {
            $referenceDate = $now;
        }

        return $this->render('workshops.html.twig', [
            'week' => $referenceDate->format('Y-m-d'),
        ]);
    }

    #[Route(path: [
        'pl' => 'warsztaty/{slug}',
        'en' => 'workshops/{slug}',
    ], name: 'workshop_by_slug')]
    public function workshopBySlug(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $lesson = $this->findLessonBySlug($slug, $request, $entityManager);

        if ($lesson === null) {
            return $this->redirectToRoute('workshops');
        }

        $schedule = $lesson->schedule;

        return $this->render('workshops.html.twig', [
            'week' => $this->resolveBrowsedWeek($request, $schedule),
            'openSlug' => $lesson->getMetadata()
                ->slug,
            'openDate' => $schedule->format('Y-m-d'),
            'openHour' => $schedule->format('H:i'),
        ]);
    }

    /**
     * Keeps the calendar/list positioned on the week the user was already browsing
     * when they open a workshop's details, instead of snapping to the week of the
     * opened workshop. Falls back to the workshop's own week when there is no
     * browsing context (e.g. a direct/shared link).
     */
    private function resolveBrowsedWeek(Request $request, \DateTimeImmutable $scheduleFallback): string
    {
        $weekParam = $request->query->get('week');

        if ($weekParam) {
            try {
                return (new \DateTimeImmutable($weekParam))->format('Y-m-d');
            } catch (\Exception) {
                // Fall through to the workshop's own week.
            }
        }

        return $scheduleFallback->format('Y-m-d');
    }

    private function findLessonBySlug(string $slug, Request $request, EntityManagerInterface $entityManager): ?Lesson
    {
        $now = Clock::get()->now();
        $date = $request->query->get('date');
        $hour = $request->query->get('hour');

        if ($date && $hour) {
            try {
                $schedule = new \DateTimeImmutable($date . ' ' . $hour);

                $query = $entityManager->createQuery(<<<DQL
                    SELECT l
                    FROM App\Entity\Lesson l
                    JOIN l.metadata m
                    WHERE m.slug = :slug
                    AND l.schedule = :schedule
                    AND l.status = 'active'
                    DQL)
                    ->setParameter('slug', $slug)
                    ->setParameter('schedule', $schedule);

                /** @var Lesson|null $lesson */
                $lesson = $query->getOneOrNullResult();

                if ($lesson) {
                    return $lesson;
                }
            } catch (\Exception) {
                // Fall through to current-week / next-upcoming lookups.
            }
        }

        $startDate = $now;
        $endDate = $startDate->modify('+7 days');

        $query = $entityManager->createQuery(<<<DQL
            SELECT l
            FROM App\Entity\Lesson l
            JOIN l.metadata m
            WHERE m.slug = :slug
            AND l.schedule BETWEEN :start AND :end
            AND l.status = 'active'
            ORDER BY l.schedule ASC
            DQL)
            ->setParameter('slug', $slug)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        /** @var Lesson|null $lesson */
        $lesson = $query->getOneOrNullResult();

        if ($lesson) {
            return $lesson;
        }

        $query = $entityManager->createQuery(<<<DQL
            SELECT l
            FROM App\Entity\Lesson l
            JOIN l.metadata m
            WHERE m.slug = :slug
            AND l.status = 'active'
            AND l.schedule >= :now
            ORDER BY l.schedule ASC
            DQL)
            ->setParameter('slug', $slug)
            ->setParameter('now', $now);

        /** @var Lesson|null $lesson */
        $lesson = $query->getOneOrNullResult();

        return $lesson;
    }
}
