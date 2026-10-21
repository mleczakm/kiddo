<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Service\RecommendationProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageAction extends AbstractController
{
    #[Route(path: '/', name: 'homepage')]
    public function __invoke(RecommendationProvider $recommendations): Response
    {
        return $this->render('homepage.html.twig', [
            'recommendations' => $recommendations->getPublished(),
            'page' => [
                'title' => 'Homepage',
                'description' => 'Welcome to the homepage.',
            ],
        ]);
    }
}
