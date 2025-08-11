<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\User;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileAction extends AbstractController
{
    #[Route(path: [
        'en' => '/profile',
        'pl' => '/profil',
    ], name: 'profile')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager,
        #[CurrentUser]
        User $user,
    ): Response {
        return $this->render(
            'profile.html.twig',
            [
                'page' => [
                    'title' => 'profile.title',
                    'description' => 'profile.description',
                ],
            ]
        );
    }
}
