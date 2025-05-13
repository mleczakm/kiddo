<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use function Symfony\Component\Translation\t;

class WorkshopsAction extends AbstractController
{
    #[Route(path: [
        'pl' => '/warsztaty',
        'en' => 'workshops',
    ], name: 'workshops')]
    public function __invoke(): Response
    {
        return $this->render(
            'workshops.html.twig',
            [
                'page' => [
                    'title' => t('Workshops'),
                    'description' => 'Welcome to the homepage.',
                ],
            ]
        );
    }
}
