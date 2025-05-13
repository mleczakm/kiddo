<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardAction extends AbstractController
{
    #[Route(path: '/panel', name: 'dashboard')]
    public function __invoke(): Response
    {
        return $this->render(
            'dashboard.html.twig',
            [
                'page' => [
                    'title' => 'Homepage',
                    'description' => 'Welcome to the homepage.',
                ],
            ]
        );
    }
}
