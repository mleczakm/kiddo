<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsletterConfirmationAction extends AbstractController
{
    #[Route(path: [
        'en' => '/newsletter-confirmed',
        'pl' => '/newsletter-potwierdzony',
    ], name: 'newsletter_confirmed')]
    public function __invoke(): Response
    {
        return $this->render('newsletter_confirmed.html.twig');
    }
}
