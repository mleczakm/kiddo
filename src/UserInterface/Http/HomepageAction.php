<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageAction extends AbstractController
{
    // Default homepage for non-Class Council tenants
    #[Route(
        path: '/',
        name: 'homepage',
        condition: "not (request.attributes.get('_tenant') and request.attributes.get('_tenant').getName() matches '/classpay/i')"
    )]
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
