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
    public function __invoke(EntityManagerInterface $entityManager, Request $request): Response
    {
        $weekParam = $request->query->get('week');
        $now = Clock::get()->now();

        // If the week parameter is provided, use it as the reference date
        if ($weekParam) {
            try {
                $referenceDate = new \DateTimeImmutable($weekParam);
            } catch (\Exception) {
                $referenceDate = $now;
            }
        } else {
            $referenceDate = $now;
        }

        $startDate = $referenceDate;
        $endDate = $startDate->modify('+7 days');

        $query = $entityManager->createQuery(<<<DQL
            SELECT l
            FROM App\Entity\Lesson l
            LEFT JOIN l.bookings b WITH b.status = 'active'
            WHERE l.metadata.schedule BETWEEN :start AND :end
            AND l.status = 'active'
            ORDER BY l.metadata.schedule ASC
            DQL)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        /** @var Lesson[] $lessons */
        $lessons = $query->getResult();

        return $this->render('workshops.html.twig', [
            'workshops' => $lessons,
            'weekStart' => $startDate,
            'weekEnd' => $endDate,
            'currentWeek' => $now->format('Y-m-d'),
        ]);
    }
}
