<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageAction extends AbstractController
{
    #[Route(path: '/', name: 'homepage')]
    public function __invoke(): Response
    {
        return $this->render(
            'homepage.html.twig',
            [
                'page' => [
                    'title' => 'Homepage',
                    'description' => 'Welcome to the homepage.',
                ],
            ]
        );
    }
}
