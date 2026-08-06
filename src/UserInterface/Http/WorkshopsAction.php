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

        $schedule = $lesson->getMetadata()
            ->schedule;

        return $this->render('workshops.html.twig', [
            'week' => $schedule->format('Y-m-d'),
            'openSlug' => $lesson->getMetadata()
                ->slug,
            'openDate' => $schedule->format('Y-m-d'),
            'openHour' => $schedule->format('H:i'),
        ]);
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
                    WHERE l.metadata.slug = :slug
                    AND l.metadata.schedule = :schedule
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
            WHERE l.metadata.slug = :slug
            AND l.metadata.schedule BETWEEN :start AND :end
            AND l.status = 'active'
            ORDER BY l.metadata.schedule ASC
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
            WHERE l.metadata.slug = :slug
            AND l.status = 'active'
            AND l.metadata.schedule >= :now
            ORDER BY l.metadata.schedule ASC
            DQL)
            ->setParameter('slug', $slug)
            ->setParameter('now', $now);

        /** @var Lesson|null $lesson */
        $lesson = $query->getOneOrNullResult();

        return $lesson;
    }
}
