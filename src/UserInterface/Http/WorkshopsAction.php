<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorkshopsAction extends AbstractController
{
    #[Route(path: [
        'pl' => '/warsztaty',
        'en' => 'workshops',
    ], name: 'workshops')]
    public function __invoke(EntityManagerInterface $entityManager, Request $request): Response
    {
        $weekParam = $request->query->get('week');
        $now = new \DateTimeImmutable();

        // If week parameter is provided, use it as the reference date
        if ($weekParam) {
            try {
                $referenceDate = new \DateTimeImmutable($weekParam);
            } catch (\Exception) {
                $referenceDate = $now;
            }
        } else {
            // If today is Saturday, show next week's lessons
            $referenceDate = (int) $now->format('N') === 6 ?
                $now->modify('next monday') :
                $now->modify('monday this week');
        }

        $startDate = $referenceDate->modify('monday this week');
        $endDate = (clone $startDate)->modify('sunday this week 23:59:59');

        $query = $entityManager->createQuery('
            SELECT l
            FROM App\Entity\Lesson l
            WHERE l.metadata.schedule BETWEEN :start AND :end
            ORDER BY l.metadata.schedule ASC
        ')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        /** @var Lesson[] $lessons */
        $lessons = $query->getResult();

        // Convert lessons to array and sort by schedule time
        $workshops = [];
        foreach ($lessons as $lesson) {
            $workshops[] = $lesson;
        }

        // Sort workshops by schedule time
        usort($workshops, fn(Lesson $a, Lesson $b) => $a->getMetadata()->schedule <=> $b->getMetadata()->schedule);

        return $this->render('workshops.html.twig', [
            'workshops' => $workshops,
            'weekStart' => $startDate,
            'weekEnd' => $endDate,
            'currentWeek' => $now->format('Y-m-d'),
        ]);
    }
}
