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
        // This is a placeholder for the homepage action.
        return $this->render('base.html.twig');
    }
}
