<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileAction extends AbstractController
{
    #[Route(path: [
        'en' => '/profile',
        'pl' => '/profil',
    ], name: 'profile')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(): Response
    {
        return $this->render('panel/personal.html.twig', [
            'page' => [
                'title' => 'profile.title',
                'description' => 'profile.description',
            ],
        ]);
    }
}
