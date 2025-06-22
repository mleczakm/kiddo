<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageAction extends AbstractController
{
    #[Route(path: '/', name: 'homepage')]
    public function __invoke(EntityManagerInterface $entityManager): Response
    {
        $now = new \DateTimeImmutable();
        $startDate = $now->modify('today');
        $endDate = $now->modify('+30 days');

        $query = $entityManager->createQuery(<<<DQL
            SELECT l
            FROM App\Entity\Lesson l
            LEFT JOIN l.bookings b WITH b.status = 'confirmed'
            WHERE l.metadata.schedule BETWEEN :start AND :end
            GROUP BY l.id
            ORDER BY l.metadata.schedule ASC
            DQL)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setMaxResults(3);

        /** @var Lesson[] $workshops */
        $workshops = $query->getResult();

        return $this->render(
            'homepage.html.twig',
            [
                'page' => [
                    'title' => 'Homepage',
                    'description' => 'Welcome to the homepage.',
                ],
                'workshops' => $workshops,
            ]
        );
    }
}
