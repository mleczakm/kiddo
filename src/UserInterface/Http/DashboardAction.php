<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\User;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
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
        return $this->render(
            'dashboard.html.twig',
            [
                'page' => [
                    'title' => 'dashboard.title',
                    'description' => 'dashboard.description',
                ],
            ]
        );
    }
}
